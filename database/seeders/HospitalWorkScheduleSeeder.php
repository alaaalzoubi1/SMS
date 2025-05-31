<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\HospitalWorkSchedule;    

class HospitalWorkScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        HospitalWorkSchedule::create([
            'hospital_id' => 1,
            'day_of_week' => 'monday',
            'start_time' => '08:00',
            'end_time' => '06:00',
        ]);
    }
}
