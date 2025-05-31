<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DoctorWorkSchedule;

class DoctorWorkScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DoctorWorkSchedule::create([
            'doctor_id' => 1,
            'day_of_week' => 'tuesday',
            'start_time' => '09:00',
            'end_time' => '02:00',
        ]);
    }
}
