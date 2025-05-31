<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DoctorReservation;

class DoctorReservationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DoctorReservation::create([
            'user_id' => 1,
            'doctor_service_id' => 1,
            'doctor_id' => 1,
            'date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
            'status' => 'pending',
        ]);
    }
}
