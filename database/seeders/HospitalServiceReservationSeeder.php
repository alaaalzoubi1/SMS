<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\HospitalServiceReservation;

class HospitalServiceReservationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        HospitalServiceReservation::create([
            'user_id' => 1,
            'hospital_service_id' => 1,
            'hospital_id' => 1,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'pending',
        ]);
    }
}
