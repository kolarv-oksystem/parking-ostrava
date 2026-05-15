<?php

namespace App\Services;

use App\Models\DevicePresence;
use App\Models\ParkingEvent;
use App\Models\ParkingState;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ParkingStateService
{
    public function getStatus(?string $deviceId = null): array
    {
        $state = $this->ensureState();
        $payload = $this->statusPayload($state);

        if ($deviceId !== null && $deviceId !== '') {
            $presence = DevicePresence::query()->find($deviceId);
            $payload['device_is_parked'] = (bool) ($presence && $presence->is_parked);
        }

        return $payload;
    }

    public function recentEvents(int $limit = 15): array
    {
        $resolvedLimit = max(1, min(50, $limit));

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
                ];
            })
            ->values()
            ->all();
    }

    public function arrive(
        string $deviceId,
        string $userName,
        float $latitude,
        float $longitude,
        string $actor = 'public'
    ): array
    {
        return DB::transaction(function () use ($deviceId, $userName, $latitude, $longitude, $actor) {
            $state = ParkingState::query()->lockForUpdate()->findOrFail(1);
            $this->assertWithinAllowedArea($latitude, $longitude);

            if ($state->free_spots <= 0) {
                throw new DomainException('Žádné volné místo není k dispozici.');
            }

            $oldValue = $state->free_spots;
            $newValue = $oldValue - 1;

            $state->free_spots = $newValue;
            $state->updated_by = $userName;
            $state->version = $state->version + 1;
            $state->save();

            DevicePresence::query()->updateOrCreate(
                ['device_id' => $deviceId],
                [
                    'is_parked' => true,
                    'blocks_untracked_leave_until_arrive' => false,
                    'last_action_at' => Carbon::now(),
                ]
            );

            $this->logEvent('arrive', -1, $oldValue, $newValue, $actor, $deviceId, $userName);

            return $this->statusPayload($state);
        });
    }

    public function leave(
        string $deviceId,
        string $userName,
        float $latitude,
        float $longitude,
        string $actor = 'public'
    ): array
    {
        return DB::transaction(function () use ($deviceId, $userName, $latitude, $longitude, $actor) {
            $state = ParkingState::query()->lockForUpdate()->findOrFail(1);
            $this->assertWithinAllowedArea($latitude, $longitude);

            $oldValue = $state->free_spots;
            $newValue = min($state->capacity_total, $oldValue + 1);

            $state->free_spots = $newValue;
            $state->updated_by = $userName;
            $state->version = $state->version + 1;
            $state->save();

            DevicePresence::query()->updateOrCreate(
                ['device_id' => $deviceId],
                [
                    'is_parked' => false,
                    'blocks_untracked_leave_until_arrive' => false,
                    'last_action_at' => Carbon::now(),
                ]
            );

            $this->logEvent('leave', 1, $oldValue, $newValue, $actor, $deviceId, $userName);

            return $this->statusPayload($state);
        });
    }

    public function setFreeSpots(int $freeSpots, string $password, string $actor = 'admin'): array
    {
        return DB::transaction(function () use ($freeSpots, $password, $actor) {
            $state = ParkingState::query()->lockForUpdate()->findOrFail(1);

            if (!Hash::check($password, $state->manual_update_password_hash)) {
                throw new DomainException('Neplatné heslo pro ruční úpravu.');
            }

            if ($freeSpots < 0 || $freeSpots > $state->capacity_total) {
                throw new DomainException('Počet volných míst je mimo povolený rozsah.');
            }

            $oldValue = $state->free_spots;
            $state->free_spots = $freeSpots;
            $state->updated_by = $actor;
            $state->version = $state->version + 1;
            $state->save();

            $this->logEvent(
                'set_manual',
                $freeSpots - $oldValue,
                $oldValue,
                $freeSpots,
                $actor,
                null,
                null
            );

            return $this->statusPayload($state);
        });
    }

    public function nightReset(string $actor = 'cron'): array
    {
        return DB::transaction(function () use ($actor) {
            $state = ParkingState::query()->lockForUpdate()->findOrFail(1);

            $oldValue = $state->free_spots;
            $newValue = $state->capacity_total;

            $state->free_spots = $newValue;
            $state->updated_by = $actor;
            $state->version = $state->version + 1;
            $state->save();

            DevicePresence::query()->update([
                'is_parked' => false,
                'blocks_untracked_leave_until_arrive' => false,
                'last_action_at' => Carbon::now(),
            ]);

            $this->logEvent(
                'reset_nightly',
                $newValue - $oldValue,
                $oldValue,
                $newValue,
                $actor,
                null,
                null
            );

            return $this->statusPayload($state);
        });
    }

    public function initOrReseed(int $capacityTotal, string $manualPassword, ?int $freeSpots, string $actor = 'admin'): array
    {
        return DB::transaction(function () use ($capacityTotal, $manualPassword, $freeSpots, $actor) {
            $capacityTotal = max(1, $capacityTotal);
            $resolvedFreeSpots = is_null($freeSpots) ? $capacityTotal : $freeSpots;

            if ($resolvedFreeSpots < 0 || $resolvedFreeSpots > $capacityTotal) {
                throw new DomainException('Počet volných míst musí být mezi 0 a kapacitou.');
            }

            $state = ParkingState::query()->lockForUpdate()->find(1);
            $oldValue = $state ? $state->free_spots : $capacityTotal;

            if (!$state) {
                $state = new ParkingState();
                $state->id = 1;
                $state->version = 0;
            }

            $state->capacity_total = $capacityTotal;
            $state->free_spots = $resolvedFreeSpots;
            $state->updated_by = $actor;
            $state->version = $state->version + 1;
            $state->manual_update_password_hash = Hash::make($manualPassword);
            $state->save();

            DevicePresence::query()->update([
                'is_parked' => false,
                'blocks_untracked_leave_until_arrive' => false,
                'last_action_at' => Carbon::now(),
            ]);

            $this->logEvent(
                'set_manual',
                $resolvedFreeSpots - $oldValue,
                $oldValue,
                $resolvedFreeSpots,
                $actor,
                null,
                null
            );

            return $this->statusPayload($state);
        });
    }

    private function ensureState(): ParkingState
    {
        $state = ParkingState::query()->find(1);
        if ($state) {
            return $state;
        }

        $capacity = max(1, (int) config('parking.capacity', 20));
        $password = (string) config('parking.manual_password', 'change-me');

        return ParkingState::query()->create([
            'id' => 1,
            'capacity_total' => $capacity,
            'free_spots' => $capacity,
            'updated_by' => 'bootstrap',
            'version' => 1,
            'manual_update_password_hash' => Hash::make($password),
        ]);
    }

    private function logEvent(
        string $action,
        int $delta,
        int $oldValue,
        int $newValue,
        ?string $actor,
        ?string $deviceId,
        ?string $userName
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

    private function statusPayload(ParkingState $state): array
    {
        return [
            'capacity_total' => (int) $state->capacity_total,
            'free_spots' => (int) $state->free_spots,
            'updated_at' => optional($state->updated_at)->toIso8601String(),
            'updated_by' => $state->updated_by,
            'version' => (int) $state->version,
        ];
    }
}
