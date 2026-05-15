<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingLeaveFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @var string */
    private $deviceA = 'dev_test_device_aaa';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('parking.capacity', 6);
        config()->set('parking.reserved_service_spots_count', 1);
        config()->set('parking.allowed_lat', 50.087451);
        config()->set('parking.allowed_lng', 14.420671);
        config()->set('parking.allowed_radius_meters', 250);
    }

    private function bootstrapParkingState(): void
    {
        $this->getJson('/api/status?device_id=' . urlencode($this->deviceA));
    }

    private function togglePayload(array $overrides = []): array
    {
        return array_merge([
            'device_id' => $this->deviceA,
            'name' => 'Test User',
            'spot_number' => 2,
            'latitude' => 50.087451,
            'longitude' => 14.420671,
            'accuracy' => 10.5,
        ], $overrides);
    }

    public function test_status_contains_spots_and_reserved_first_spot()
    {
        $this->bootstrapParkingState();

        $this->getJson('/api/status')
            ->assertOk()
            ->assertJsonPath('capacity_total', 6)
            ->assertJsonPath('free_spots', 6)
            ->assertJsonCount(6, 'spots')
            ->assertJsonPath('spots.0.spot_number', 1)
            ->assertJsonPath('spots.0.is_reserved_service', true);
    }

    public function test_spot_toggle_occupy_and_release()
    {
        $this->bootstrapParkingState();

        $this->postJson('/api/spots/toggle', $this->togglePayload([
            'spot_number' => 2,
            'name' => 'Jana',
        ]))->assertOk()->assertJsonPath('free_spots', 5);

        $this->postJson('/api/spots/toggle', $this->togglePayload([
            'spot_number' => 2,
            'name' => 'Jana',
        ]))->assertOk()->assertJsonPath('free_spots', 6);
    }

    public function test_spots_2_to_6_require_gps_for_occupy()
    {
        $this->bootstrapParkingState();

        $this->postJson('/api/spots/toggle', [
            'device_id' => $this->deviceA,
            'name' => 'Jana',
            'spot_number' => 3,
        ])->assertStatus(422)->assertJsonFragment([
            'message' => 'Pro tuto akci je nutné ověření GPS polohy.',
        ]);
    }

    public function test_spot_1_service_vehicle_can_be_occupied_without_gps()
    {
        $this->bootstrapParkingState();

        $this->postJson('/api/spots/toggle', [
            'device_id' => $this->deviceA,
            'name' => 'Jana',
            'spot_number' => 1,
            'vehicle_type' => 'service',
        ])->assertOk()
            ->assertJsonPath('spots.0.is_occupied', true)
            ->assertJsonPath('spots.0.occupied_by_name', 'Jana')
            ->assertJsonPath('spots.0.is_reserved_service', true);
    }

    public function test_spot_1_private_vehicle_requires_gps_when_unspecified()
    {
        $this->bootstrapParkingState();

        $this->postJson('/api/spots/toggle', [
            'device_id' => $this->deviceA,
            'name' => 'Jana',
            'spot_number' => 1,
        ])->assertStatus(422)->assertJsonFragment([
            'message' => 'Pro tuto akci je nutné ověření GPS polohy.',
        ]);
    }

    public function test_spot_1_private_vehicle_can_be_occupied_with_gps()
    {
        $this->bootstrapParkingState();

        $this->postJson('/api/spots/toggle', [
            'device_id' => $this->deviceA,
            'name' => 'Jana',
            'spot_number' => 1,
            'vehicle_type' => 'private',
            'latitude' => 50.087451,
            'longitude' => 14.420671,
            'accuracy' => 10,
        ])->assertOk()
            ->assertJsonPath('spots.0.is_occupied', true)
            ->assertJsonPath('spots.0.is_reserved_service', false);
    }

    public function test_service_reservation_can_be_toggled_for_first_spot()
    {
        $this->bootstrapParkingState();

        $this->postJson('/api/spots/toggle-service-reservation', [
            'device_id' => $this->deviceA,
            'name' => 'Jana',
            'spot_number' => 1,
        ])->assertOk()->assertJsonPath('spots.0.is_reserved_service', false);

        $this->postJson('/api/spots/toggle-service-reservation', [
            'device_id' => $this->deviceA,
            'name' => 'Jana',
            'spot_number' => 1,
        ])->assertOk()->assertJsonPath('spots.0.is_reserved_service', true);
    }

    public function test_recent_events_include_spot_number()
    {
        $this->bootstrapParkingState();

        $this->postJson('/api/spots/toggle', $this->togglePayload([
            'spot_number' => 2,
            'name' => 'Jana',
        ]))->assertOk();

        $this->getJson('/api/events?limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.user_name', 'Jana')
            ->assertJsonPath('events.0.spot_number', 2)
            ->assertJsonPath('events.0.vehicle_type', 'private');
    }
}
