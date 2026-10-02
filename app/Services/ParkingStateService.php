<?php

namespace App\Services;

use App\Models\DevicePresence;
use App\Models\ParkingEvent;
use App\Models\ParkingSpot;
use App\Models\ParkingState;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ParkingStateService
{
    private const MAX_SPOTS = 6;

    public function getStatus(?string $deviceId = null): array
    {
        $state = $this->ensureState();
        $spots = $this->ensureSpots((int) $state->capacity_total);
        $payload = $this->statusPayload($state, $spots);

        if ($deviceId !== null && $deviceId !== '') {
            /** @var ParkingSpot|null $deviceSpot */
            $deviceSpot = $spots->first(function (ParkingSpot $spot) use ($deviceId) {
                return $spot->is_occupied && $spot->occupied_by_device_id === $deviceId;
            });
            $payload['device_is_parked'] = $spots->contains(function (ParkingSpot $spot) use ($deviceId) {
                return $spot->is_occupied && $spot->occupied_by_device_id === $deviceId;
            });
            $payload['device_spot_number'] = $deviceSpot ? (int) $deviceSpot->spot_number : null;
        }

        return $payload;
    }

    public function recentEvents(int $limit = 15): array
    {
        $resolvedLimit = max(1, min(100, $limit));

        return ParkingEvent::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($resolvedLimit)
            ->get([
                'id',
                'action',
                'delta',
                'old_value',
                'new_value',
                'created_at',
                'actor',
                'user_name',
                'device_id',
                'spot_number',
                'is_reserved_service',
                'vehicle_type',
            ])
            ->map(function (ParkingEvent $event) {
                return [
                    'id' => (int) $event->id,
                    'action' => $event->action,
                    'delta' => (int) $event->delta,
                    'old_value' => (int) $event->old_value,
                    'new_value' => (int) $event->new_value,
                    'created_at' => $event->created_at
                        ? Carbon::parse($event->created_at)->toIso8601String()
                        : null,
                    'actor' => $event->actor,
                    'user_name' => $event->user_name,
                    'device_id' => $event->device_id,
                    'spot_number' => $event->spot_number ? (int) $event->spot_number : null,
                    'is_reserved_service' => (bool) $event->is_reserved_service,
                    'vehicle_type' => $event->vehicle_type,
                ];
            })
            ->values()
            ->all();
    }

    public function toggleSpotOccupancy(
        string $deviceId,
        string $userName,
        int $spotNumber,
        ?float $latitude,
        ?float $longitude,
        string $actor = 'public',
        ?string $vehicleType = null,
        bool $skipAutoRelease = false
    ): array {
        return DB::transaction(function () use ($deviceId, $userName, $spotNumber, $latitude, $longitude, $actor, $vehicleType, $skipAutoRelease) {
            $state = ParkingState::query()->lockForUpdate()->findOrFail(1);
            $this->ensureSpots((int) $state->capacity_total);
            $oldValue = (int) $state->free_spots;
            $reservedServiceCount = $this->resolvedReservedServiceSpotsCount((int) $state->capacity_total);

            /** @var ParkingSpot|null $spot */
            $spot = ParkingSpot::query()
                ->where('spot_number', $spotNumber)
                ->lockForUpdate()
                ->first();

            if (!$spot) {
                throw new DomainException('Vybrané místo neexistuje.');
            }

            $previousDeviceId = null;
            $vehicleTypeForEvent = null;

            if ($spot->is_occupied) {
                $vehicleTypeForEvent = (bool) $spot->is_reserved_service ? 'service' : 'private';
                $previousDeviceId = $spot->occupied_by_device_id;
                $spot->is_occupied = false;
                $spot->occupied_by_name = null;
                $spot->occupied_by_device_id = null;
                $spot->occupied_at = null;
                $spot->skip_auto_release = false;
                $spot->save();

                $action = 'spot_leave';
                $delta = 1;
            } else {
                $inServiceZone = $spotNumber <= $reservedServiceCount;
                if ($inServiceZone) {
                    $effectiveType = $vehicleType === 'service' ? 'service' : 'private';
                    $vehicleTypeForEvent = $effectiveType;
                    if ($effectiveType === 'service') {
                        $spot->is_reserved_service = true;
                        $spot->save();
                    } else {
                        $spot->is_reserved_service = false;
                        $spot->save();
                        $this->assertCoordinatesProvided($latitude, $longitude);
                        $this->assertWithinAllowedArea((float) $latitude, (float) $longitude);
                    }
                } else {
                    $vehicleTypeForEvent = 'private';
                    $this->assertCoordinatesProvided($latitude, $longitude);
                    $this->assertWithinAllowedArea((float) $latitude, (float) $longitude);
                }

                $spot->is_occupied = true;
                $spot->occupied_by_name = $userName;
                $spot->occupied_by_device_id = $deviceId;
                $spot->occupied_at = Carbon::now();
                $spot->skip_auto_release = $skipAutoRelease;
                $spot->save();

                $action = 'spot_arrive';
                $delta = -1;
            }

            $updatedSpots = ParkingSpot::query()->orderBy('spot_number')->get();
            $freeSpots = $this->countFreeSpots($updatedSpots);

            $state->free_spots = $freeSpots;
            $state->updated_by = $userName;
            $state->version = $state->version + 1;
            $state->save();

            if ($previousDeviceId) {
                DevicePresence::query()->updateOrCreate(
                    ['device_id' => $previousDeviceId],
                    [
                        'is_parked' => false,
                        'blocks_untracked_leave_until_arrive' => false,
                        'last_action_at' => Carbon::now(),
                    ]
                );
            }

            DevicePresence::query()->updateOrCreate(
                ['device_id' => $deviceId],
                [
                    'is_parked' => $updatedSpots->contains(function (ParkingSpot $row) use ($deviceId) {
                        return $row->is_occupied && $row->occupied_by_device_id === $deviceId;
                    }),
                    'blocks_untracked_leave_until_arrive' => false,
                    'last_action_at' => Carbon::now(),
                ]
            );

            $this->logEvent(
                $action,
                $delta,
                $oldValue,
                $freeSpots,
                $actor,
                $deviceId,
                $userName,
                (int) $spot->spot_number,
                (bool) $spot->is_reserved_service,
                $vehicleTypeForEvent
            );

            return $this->statusPayload($state, $updatedSpots);
        });
    }

    public function toggleServiceReservation(
        string $deviceId,
        string $userName,
        int $spotNumber,
        string $actor = 'public'
    ): array {
        return DB::transaction(function () use ($deviceId, $userName, $spotNumber, $actor) {
            $state = ParkingState::query()->lockForUpdate()->findOrFail(1);
            $this->ensureSpots((int) $state->capacity_total);
            $oldValue = (int) $state->free_spots;

            $reservedCount = $this->resolvedReservedServiceSpotsCount((int) $state->capacity_total);
            if ($spotNumber < 1 || $spotNumber > $reservedCount) {
                throw new DomainException('Služební rezervaci lze měnit jen na vyhrazených místech.');
            }

            /** @var ParkingSpot|null $spot */
            $spot = ParkingSpot::query()
                ->where('spot_number', $spotNumber)
                ->lockForUpdate()
                ->first();

            if (!$spot) {
                throw new DomainException('Vybrané místo neexistuje.');
            }

            if ($spot->is_occupied && !$spot->is_reserved_service) {
                throw new DomainException('Obsazené osobní místo nelze označit jako služební rezervaci.');
            }

            $spot->is_reserved_service = !$spot->is_reserved_service;
            $spot->save();

            $spots = ParkingSpot::query()->orderBy('spot_number')->get();
            $freeSpots = $this->countFreeSpots($spots);

            $state->free_spots = $freeSpots;
            $state->updated_by = $userName;
            $state->version = $state->version + 1;
            $state->save();

            $this->logEvent(
                $spot->is_reserved_service ? 'service_reserve_on' : 'service_reserve_off',
                0,
                $oldValue,
                $freeSpots,
                $actor,
                $deviceId,
                $userName,
                (int) $spot->spot_number,
                (bool) $spot->is_reserved_service,
                null
            );

            return $this->statusPayload($state, $spots);
        });
    }

    public function setFreeSpots(int $freeSpots, string $password, string $actor = 'admin'): array
    {
        return DB::transaction(function () use ($freeSpots, $password, $actor) {
            $state = ParkingState::query()->lockForUpdate()->findOrFail(1);
            $this->ensureSpots((int) $state->capacity_total);

            if (!Hash::check($password, $state->manual_update_password_hash)) {
                throw new DomainException('Neplatné heslo pro ruční úpravu.');
            }

            if ($freeSpots < 0 || $freeSpots > $state->capacity_total) {
                throw new DomainException('Počet volných míst je mimo povolený rozsah.');
            }

            $spots = ParkingSpot::query()->orderBy('spot_number')->lockForUpdate()->get();
            $targetOccupied = (int) $state->capacity_total - $freeSpots;
            $currentOccupied = $spots->where('is_occupied', true)->count();

            if ($targetOccupied > $currentOccupied) {
                $toOccupy = $targetOccupied - $currentOccupied;
                $spots->where('is_occupied', false)->take($toOccupy)->each(function (ParkingSpot $spot) {
                    $spot->is_occupied = true;
                    $spot->occupied_by_name = null;
                    $spot->occupied_by_device_id = null;
                    $spot->occupied_at = Carbon::now();
                    $spot->skip_auto_release = false;
                    $spot->save();
                });
            } elseif ($targetOccupied < $currentOccupied) {
                $toRelease = $currentOccupied - $targetOccupied;
                $spots->where('is_occupied', true)->sortByDesc('spot_number')->take($toRelease)->each(function (ParkingSpot $spot) {
                    $spot->is_occupied = false;
                    $spot->occupied_by_name = null;
                    $spot->occupied_by_device_id = null;
                    $spot->occupied_at = null;
                    $spot->skip_auto_release = false;
                    $spot->save();
                });
            }

            DevicePresence::query()->update([
                'is_parked' => false,
                'blocks_untracked_leave_until_arrive' => false,
                'last_action_at' => Carbon::now(),
            ]);

            $updatedSpots = ParkingSpot::query()->orderBy('spot_number')->get();
            $oldValue = (int) $state->free_spots;
            $newValue = $this->countFreeSpots($updatedSpots);

            $state->free_spots = $newValue;
            $state->updated_by = $actor;
            $state->version = $state->version + 1;
            $state->save();

            $this->logEvent(
                'set_manual',
                $newValue - $oldValue,
                $oldValue,
                $newValue,
                $actor,
                null,
                null,
                null,
                false,
                null
            );

            return $this->statusPayload($state, $updatedSpots);
        });
    }

    public function initOrReseed(int $capacityTotal, string $manualPassword, ?int $freeSpots, string $actor = 'admin'): array
    {
        return DB::transaction(function () use ($capacityTotal, $manualPassword, $freeSpots, $actor) {
            $capacityTotal = $this->resolvedCapacity($capacityTotal);
            $resolvedFreeSpots = is_null($freeSpots) ? $capacityTotal : $freeSpots;

            if ($resolvedFreeSpots < 0 || $resolvedFreeSpots > $capacityTotal) {
                throw new DomainException('Počet volných míst musí být mezi 0 a kapacitou.');
            }

            $state = ParkingState::query()->lockForUpdate()->find(1);
            $oldValue = $state ? (int) $state->free_spots : $capacityTotal;

            if (!$state) {
                $state = new ParkingState();
                $state->id = 1;
                $state->version = 0;
            }

            $state->capacity_total = $capacityTotal;
            $state->updated_by = $actor;
            $state->version = $state->version + 1;
            $state->manual_update_password_hash = Hash::make($manualPassword);
            $state->save();

            $spots = $this->ensureSpots($capacityTotal);
            $targetOccupied = $capacityTotal - $resolvedFreeSpots;
            $occupiedCounter = 0;

            $spots->sortBy('spot_number')->values()->each(function (ParkingSpot $spot) use ($targetOccupied, &$occupiedCounter) {
                $shouldBeOccupied = $occupiedCounter < $targetOccupied;
                if ($shouldBeOccupied) {
                    $occupiedCounter++;
                }

                $spot->is_occupied = $shouldBeOccupied;
                $spot->occupied_by_name = null;
                $spot->occupied_by_device_id = null;
                $spot->occupied_at = $shouldBeOccupied ? Carbon::now() : null;
                $spot->skip_auto_release = false;
                $spot->save();
            });

            DevicePresence::query()->update([
                'is_parked' => false,
                'blocks_untracked_leave_until_arrive' => false,
                'last_action_at' => Carbon::now(),
            ]);

            $updatedSpots = ParkingSpot::query()->orderBy('spot_number')->get();
            $newValue = $this->countFreeSpots($updatedSpots);
            $state->free_spots = $newValue;
            $state->save();

            $this->logEvent(
                'set_manual',
                $newValue - $oldValue,
                $oldValue,
                $newValue,
                $actor,
                null,
                null,
                null,
                false,
                null
            );

            return $this->statusPayload($state, $updatedSpots);
        });
    }

    /**
     * Uvolní obsazená místa, která nemají výjimku.
     * Výjimky: služební vozidlo a místo označené „Automaticky neuvolňovat“.
     * Čas spuštění řídí externí CRON (typicky 19:00), endpoint sám hodinu nehlídá.
     */
    public function releaseSpotsAutomatically(string $actor = 'auto-release'): array
    {
        return DB::transaction(function () use ($actor) {
            $state = ParkingState::query()->lockForUpdate()->findOrFail(1);
            $this->ensureSpots((int) $state->capacity_total);

            $spots = ParkingSpot::query()->orderBy('spot_number')->lockForUpdate()->get();
            $freeBefore = $this->countFreeSpots($spots);
            $released = [];
            $kept = [];

            foreach ($spots as $spot) {
                if (!$spot->is_occupied) {
                    continue;
                }

                if ($this->isExemptFromAutoRelease($spot)) {
                    $kept[] = (int) $spot->spot_number;
                    continue;
                }

                $released[] = [
                    'spot_number' => (int) $spot->spot_number,
                    'device_id' => $spot->occupied_by_device_id,
                    'user_name' => $spot->occupied_by_name,
                    'is_reserved_service' => (bool) $spot->is_reserved_service,
                    'vehicle_type' => (bool) $spot->is_reserved_service ? 'service' : 'private',
                ];

                $spot->is_occupied = false;
                $spot->occupied_by_name = null;
                $spot->occupied_by_device_id = null;
                $spot->occupied_at = null;
                $spot->skip_auto_release = false;
                $spot->save();
            }

            $updatedSpots = ParkingSpot::query()->orderBy('spot_number')->get();

            if ($released !== []) {
                $deviceIds = [];
                foreach ($released as $row) {
                    if (is_string($row['device_id']) && $row['device_id'] !== '') {
                        $deviceIds[$row['device_id']] = $row['device_id'];
                    }
                }

                foreach ($deviceIds as $deviceId) {
                    DevicePresence::query()->updateOrCreate(
                        ['device_id' => $deviceId],
                        [
                            'is_parked' => $updatedSpots->contains(function (ParkingSpot $row) use ($deviceId) {
                                return $row->is_occupied && $row->occupied_by_device_id === $deviceId;
                            }),
                            'blocks_untracked_leave_until_arrive' => false,
                            'last_action_at' => Carbon::now(),
                        ]
                    );
                }

                $freeSpots = $this->countFreeSpots($updatedSpots);
                $state->free_spots = $freeSpots;
                $state->updated_by = $actor;
                $state->version = $state->version + 1;
                $state->save();

                $runningFree = $freeBefore;
                foreach ($released as $row) {
                    $runningFree++;
                    $this->logEvent(
                        'auto_release',
                        1,
                        $runningFree - 1,
                        $runningFree,
                        $actor,
                        $row['device_id'],
                        $row['user_name'],
                        $row['spot_number'],
                        $row['is_reserved_service'],
                        $row['vehicle_type']
                    );
                }
            }

            $payload = $this->statusPayload($state, $updatedSpots);
            $payload['released_spots'] = array_map(function (array $row) {
                return $row['spot_number'];
            }, $released);
            $payload['kept_spots'] = $kept;

            return $payload;
        });
    }

    private function isExemptFromAutoRelease(ParkingSpot $spot): bool
    {
        return (bool) $spot->skip_auto_release || (bool) $spot->is_reserved_service;
    }

    private function ensureState(): ParkingState
    {
        $state = ParkingState::query()->find(1);
        if ($state) {
            return $state;
        }

        $capacity = $this->resolvedCapacity((int) config('parking.capacity', 6));
        $password = (string) config('parking.manual_password', 'change-me');

        $state = ParkingState::query()->create([
            'id' => 1,
            'capacity_total' => $capacity,
            'free_spots' => $capacity,
            'updated_by' => 'bootstrap',
            'version' => 1,
            'manual_update_password_hash' => Hash::make($password),
        ]);

        $this->ensureSpots($capacity);

        return $state;
    }

    private function ensureSpots(int $capacityTotal): Collection
    {
        $capacityTotal = $this->resolvedCapacity($capacityTotal);
        $reservedCount = $this->resolvedReservedServiceSpotsCount($capacityTotal);
        $now = Carbon::now();

        /** @var Collection<int, ParkingSpot> $existing */
        $existing = ParkingSpot::query()
            ->orderBy('spot_number')
            ->get()
            ->keyBy('spot_number');

        for ($spotNumber = 1; $spotNumber <= $capacityTotal; $spotNumber++) {
            /** @var ParkingSpot|null $spot */
            $spot = $existing->get($spotNumber);
            if (!$spot) {
                $spot = ParkingSpot::query()->create([
                    'spot_number' => $spotNumber,
                    'is_occupied' => false,
                    'occupied_by_name' => null,
                    'occupied_by_device_id' => null,
                    'occupied_at' => null,
                    'is_reserved_service' => $spotNumber <= $reservedCount,
                    'skip_auto_release' => false,
                ]);
                $existing->put($spotNumber, $spot);
                continue;
            }

            if ($spotNumber > $reservedCount && (bool) $spot->is_reserved_service) {
                $spot->is_reserved_service = false;
                $spot->updated_at = $now;
                $spot->save();
            }
        }

        if ($existing->count() > $capacityTotal) {
            ParkingSpot::query()->where('spot_number', '>', $capacityTotal)->delete();
        }

        return ParkingSpot::query()->orderBy('spot_number')->get();
    }

    private function resolvedReservedServiceSpotsCount(int $capacityTotal): int
    {
        $configured = (int) config('parking.reserved_service_spots_count', 1);
        if ($configured < 0) {
            return 0;
        }

        return min($capacityTotal, $configured);
    }

    private function resolvedCapacity(int $capacity): int
    {
        return min(self::MAX_SPOTS, max(1, $capacity));
    }

    private function countFreeSpots(Collection $spots): int
    {
        return $spots->where('is_occupied', false)->count();
    }

    private function assertCoordinatesProvided(?float $latitude, ?float $longitude): void
    {
        if ($latitude === null || $longitude === null) {
            throw new DomainException('Pro tuto akci je nutné ověření GPS polohy.');
        }
    }

    private function logEvent(
        string $action,
        int $delta,
        int $oldValue,
        int $newValue,
        ?string $actor,
        ?string $deviceId,
        ?string $userName,
        ?int $spotNumber,
        bool $isReservedService,
        ?string $vehicleType
    ): void {
        ParkingEvent::query()->create([
            'action' => $action,
            'delta' => $delta,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'created_at' => Carbon::now(),
            'actor' => $actor,
            'user_name' => $userName,
            'device_id' => $deviceId,
            'spot_number' => $spotNumber,
            'is_reserved_service' => $isReservedService,
            'vehicle_type' => $vehicleType,
        ]);
    }

    private function assertWithinAllowedArea(float $latitude, float $longitude): void
    {
        $allowedLat = config('parking.allowed_lat');
        $allowedLng = config('parking.allowed_lng');
        $radiusMeters = config('parking.allowed_radius_meters');

        if (
            $allowedLat === null
            || $allowedLng === null
            || $allowedLat === ''
            || $allowedLng === ''
            || $radiusMeters === null
            || $radiusMeters === ''
        ) {
            throw new DomainException('GPS kontrola není nakonfigurovaná.');
        }

        $allowedLat = (float) $allowedLat;
        $allowedLng = (float) $allowedLng;
        $radiusMeters = (float) $radiusMeters;

        if ($radiusMeters <= 0) {
            throw new DomainException('Povolený GPS okruh musí být větší než 0 metrů.');
        }

        $distanceMeters = $this->distanceMeters($allowedLat, $allowedLng, $latitude, $longitude);
        if ($distanceMeters > $radiusMeters) {
            throw new DomainException('Nejste v povolené parkovací zóně.');
        }
    }

    private function distanceMeters(
        float $originLat,
        float $originLng,
        float $targetLat,
        float $targetLng
    ): float {
        $earthRadius = 6371000.0;
        $latDistance = deg2rad($targetLat - $originLat);
        $lngDistance = deg2rad($targetLng - $originLng);
        $originLatRad = deg2rad($originLat);
        $targetLatRad = deg2rad($targetLat);

        $a = sin($latDistance / 2) * sin($latDistance / 2)
            + cos($originLatRad) * cos($targetLatRad)
            * sin($lngDistance / 2) * sin($lngDistance / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function statusPayload(ParkingState $state, Collection $spots): array
    {
        $freeSpots = $this->countFreeSpots($spots);
        $capacity = $spots->count() ?: (int) $state->capacity_total;

        if ((int) $state->capacity_total !== $capacity || (int) $state->free_spots !== $freeSpots) {
            $state->capacity_total = $capacity;
            $state->free_spots = $freeSpots;
            $state->save();
        }

        return [
            'capacity_total' => $capacity,
            'free_spots' => $freeSpots,
            'updated_at' => optional($state->updated_at)->toIso8601String(),
            'updated_by' => $state->updated_by,
            'version' => (int) $state->version,
            'reserved_service_spots_count' => $this->resolvedReservedServiceSpotsCount($capacity),
            'spots' => $spots->map(function (ParkingSpot $spot) {
                return [
                    'spot_number' => (int) $spot->spot_number,
                    'is_occupied' => (bool) $spot->is_occupied,
                    'occupied_by_name' => $spot->occupied_by_name,
                    'is_reserved_service' => (bool) $spot->is_reserved_service,
                    'skip_auto_release' => (bool) $spot->skip_auto_release,
                    'occupied_at' => $spot->occupied_at ? $spot->occupied_at->toIso8601String() : null,
                ];
            })->values()->all(),
        ];
    }
}
