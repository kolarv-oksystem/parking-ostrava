<?php

namespace Database\Seeders;

use App\Models\ParkingState;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ParkingStateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $capacity = (int) env('PARKING_CAPACITY', 20);
        $capacity = max(1, $capacity);
        $password = (string) env('PARKING_MANUAL_PASSWORD', 'change-me');

        ParkingState::query()->updateOrCreate(
            ['id' => 1],
            [
                'capacity_total' => $capacity,
                'free_spots' => $capacity,
                'updated_by' => 'seed',
                'version' => 1,
                'manual_update_password_hash' => Hash::make($password),
            ]
        );
    }
}
