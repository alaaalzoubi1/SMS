<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Hospital;

class HospitalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Using factories to create 10 hospitals with related services and work schedules
        Hospital::factory()->count(10)->create()->each(function ($hospital) {
            // Create hospital services for each hospital
            \App\Models\HospitalService::factory()->count(5)->create([
                'hospital_id' => $hospital->id,
            ]);

            // Create hospital work schedules for each hospital
            \App\Models\HospitalWorkSchedule::factory()->count(7)->create([
                'hospital_id' => $hospital->id,
            ]);
        });

    }
}
