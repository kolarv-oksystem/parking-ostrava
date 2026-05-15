<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingLeaveFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @var string */
    private $deviceA = 'dev_test_device_aaa';

    /** @var string */
    private $deviceB = 'dev_test_device_bbb';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('parking.allowed_lat', 50.087451);
        config()->set('parking.allowed_lng', 14.420671);
        config()->set('parking.allowed_radius_meters', 250);
    }

    private function bootstrapParkingState(): void
    {
        $this->getJson('/api/status?device_id=' . urlencode($this->deviceA));
    }

    private function actionPayload(array $overrides = []): array
    {
        return array_merge([
            'device_id' => $this->deviceA,
            'name' => 'Test User',
            'latitude' => 50.087451,
            'longitude' => 14.420671,
            'accuracy' => 10.5,
        ], $overrides);
    }

    public function test_same_device_can_arrive_multiple_times()
    {
        $this->bootstrapParkingState();
        $before = $this->getJson('/api/status')->json('free_spots');

        $this->postJson('/api/decrement', $this->actionPayload())->assertOk();
        $this->postJson('/api/decrement', $this->actionPayload())->assertOk();

        $this->assertSame($before - 2, $this->getJson('/api/status')->json('free_spots'));
    }

    public function test_same_device_can_leave_multiple_times_without_confirm()
    {
        $this->bootstrapParkingState();
        $this->postJson('/api/decrement', $this->actionPayload(['device_id' => $this->deviceB]))->assertOk();

        $this->postJson('/api/increment', $this->actionPayload())->assertOk();
        $this->postJson('/api/increment', $this->actionPayload())->assertOk();

        $this->assertSame(
            $this->getJson('/api/status')->json('capacity_total'),
            $this->getJson('/api/status')->json('free_spots')
        );
    }

    public function test_name_is_required_for_public_actions()
    {
        $this->bootstrapParkingState();

        $this->postJson('/api/decrement', $this->actionPayload(['name' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_gps_outside_allowed_radius_is_rejected()
    {
        $this->bootstrapParkingState();

        $this->postJson('/api/decrement', $this->actionPayload([
            'latitude' => 50.100000,
            'longitude' => 14.500000,
        ]))->assertStatus(422)->assertJsonFragment([
            'message' => 'Nejste v povolené parkovací zóně.',
        ]);
    }

    public function test_recent_events_endpoint_returns_latest_records_with_user_name()
    {
        $this->bootstrapParkingState();

        $this->postJson('/api/decrement', $this->actionPayload(['name' => 'Jana']))->assertOk();
        $this->postJson('/api/increment', $this->actionPayload(['name' => 'Petr']))->assertOk();

        $this->getJson('/api/events?limit=2')
            ->assertOk()
            ->assertJsonCount(2, 'events')
            ->assertJsonPath('events.0.user_name', 'Petr')
            ->assertJsonPath('events.1.user_name', 'Jana');
    }
}
