<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\NurseReservation;

class NurseReservationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         NurseReservation::create([
            'user_id' => 1,
            'nurse_id' => 1,
            'nurse_service_id' => 1,
            'reservation_type' => 'direct',
            'location_lat' => 33,
            'location_lng' => 36,
            'status' => 'pending',
            'note' => 'Test',
            'start_at' => now()->addDays(1),
            'end_at' => now()->addDays(1)->addHour(),
        ]);
    }
}
