<?php

namespace Tests\Feature;

use App\Models\DevicePresence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingAutoReleaseTest extends TestCase
{
    use RefreshDatabase;

    /** @var string */
    private $deviceA = 'dev_test_device_aaa';

    /** @var string */
    private $deviceB = 'dev_test_device_bbb';

    /** @var string */
    private $deviceC = 'dev_test_device_ccc';

    /** @var string */
    private $releaseToken = 'release-token-for-tests';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('parking.capacity', 6);
        config()->set('parking.reserved_service_spots_count', 1);
        config()->set('parking.allowed_lat', 50.087451);
        config()->set('parking.allowed_lng', 14.420671);
        config()->set('parking.allowed_radius_meters', 250);
        config()->set('parking.auto_release_token', $this->releaseToken);
    }

    private function bootstrapParkingState(): void
    {
        $this->getJson('/api/status?device_id=' . urlencode($this->deviceA))->assertOk();
    }

    private function occupy(array $overrides = []): void
    {
        $this->postJson('/api/spots/toggle', array_merge([
            'device_id' => $this->deviceA,
            'name' => 'Test User',
            'spot_number' => 2,
            'latitude' => 50.087451,
            'longitude' => 14.420671,
            'accuracy' => 10,
        ], $overrides))->assertOk();
    }

    public function test_checkbox_flag_is_stored_only_when_occupying_and_cleared_on_leave()
    {
        $this->bootstrapParkingState();

        $this->postJson('/api/spots/toggle', [
            'device_id' => $this->deviceA,
            'name' => 'Jana',
            'spot_number' => 4,
            'latitude' => 50.087451,
            'longitude' => 14.420671,
            'skip_auto_release' => true,
        ])->assertOk()
            ->assertJsonPath('spots.3.skip_auto_release', true)
            ->assertJsonPath('spots.3.occupied_by_name', 'Jana');

        $this->postJson('/api/spots/toggle', [
            'device_id' => $this->deviceA,
            'name' => 'Jana',
            'spot_number' => 4,
            'skip_auto_release' => true,
        ])->assertOk()
            ->assertJsonPath('spots.3.is_occupied', false)
            ->assertJsonPath('spots.3.skip_auto_release', false);
    }

    public function test_occupy_without_flag_stays_releasable()
    {
        $this->bootstrapParkingState();
        $this->occupy(['name' => 'Petr', 'spot_number' => 2]);

        $this->getJson('/api/status')
            ->assertOk()
            ->assertJsonPath('spots.1.skip_auto_release', false);
    }

    public function test_auto_release_requires_configured_token()
    {
        $this->bootstrapParkingState();
        config()->set('parking.auto_release_token', '');

        $this->getJson('/api/cron/release-spots?token=' . $this->releaseToken)
            ->assertStatus(503);
    }

    public function test_auto_release_rejects_missing_or_wrong_token()
    {
        $this->bootstrapParkingState();

        $this->getJson('/api/cron/release-spots')->assertStatus(401);
        $this->getJson('/api/cron/release-spots?token=wrong-token')->assertStatus(401);
    }

    public function test_auto_release_keeps_service_vehicle_and_marked_spots()
    {
        $this->bootstrapParkingState();

        $this->occupy([
            'device_id' => $this->deviceC,
            'name' => 'Sluzebni',
            'spot_number' => 1,
            'vehicle_type' => 'service',
        ]);

        $this->occupy([
            'device_id' => $this->deviceA,
            'name' => 'Odjed',
            'spot_number' => 2,
        ]);

        $this->occupy([
            'device_id' => $this->deviceB,
            'name' => 'Zustan',
            'spot_number' => 3,
            'skip_auto_release' => true,
        ]);

        $versionBefore = (int) $this->getJson('/api/status')->json('version');

        $response = $this->getJson('/api/cron/release-spots?token=' . urlencode($this->releaseToken));
        $response->assertOk()
            ->assertJsonPath('released_spots', [2])
            ->assertJsonPath('kept_spots', [1, 3])
            ->assertJsonPath('free_spots', 4)
            ->assertJsonPath('spots.0.is_occupied', true)
            ->assertJsonPath('spots.0.occupied_by_name', 'Sluzebni')
            ->assertJsonPath('spots.1.is_occupied', false)
            ->assertJsonPath('spots.2.is_occupied', true)
            ->assertJsonPath('spots.2.occupied_by_name', 'Zustan')
            ->assertJsonPath('spots.2.skip_auto_release', true);

        $this->assertGreaterThan($versionBefore, (int) $response->json('version'));

        $this->assertFalse((bool) DevicePresence::query()->find($this->deviceA)->is_parked);
        $this->assertTrue((bool) DevicePresence::query()->find($this->deviceB)->is_parked);
        $this->assertTrue((bool) DevicePresence::query()->find($this->deviceC)->is_parked);

        $this->getJson('/api/events?limit=5')
            ->assertOk()
            ->assertJsonFragment([
                'action' => 'auto_release',
                'user_name' => 'Odjed',
                'spot_number' => 2,
            ]);
    }

    public function test_private_car_in_service_zone_is_released()
    {
        $this->bootstrapParkingState();

        $this->occupy([
            'name' => 'Soukrome',
            'spot_number' => 1,
            'vehicle_type' => 'private',
        ]);

        $this->getJson('/api/cron/release-spots?token=' . $this->releaseToken)
            ->assertOk()
            ->assertJsonPath('released_spots', [1])
            ->assertJsonPath('spots.0.is_occupied', false)
            ->assertJsonPath('spots.0.is_reserved_service', false);
    }

    public function test_auto_release_second_run_changes_nothing()
    {
        $this->bootstrapParkingState();
        $this->occupy([
            'name' => 'Zustan',
            'spot_number' => 5,
            'skip_auto_release' => true,
        ]);

        $this->getJson('/api/cron/release-spots?token=' . $this->releaseToken)
            ->assertOk()
            ->assertJsonPath('released_spots', [])
            ->assertJsonPath('kept_spots', [5]);

        $version = (int) $this->getJson('/api/status')->json('version');

        $this->getJson('/api/cron/release-spots?token=' . $this->releaseToken)
            ->assertOk()
            ->assertJsonPath('released_spots', [])
            ->assertJsonPath('kept_spots', [5])
            ->assertJsonPath('spots.4.is_occupied', true)
            ->assertJsonPath('version', $version);
    }
}
