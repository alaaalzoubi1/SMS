<?php
// database/factories/HospitalWorkScheduleFactory.php

namespace Database\Factories;

use App\Models\HospitalWorkSchedule;
use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

class HospitalWorkScheduleFactory extends Factory
{
    protected $model = HospitalWorkSchedule::class;

    public function definition()
    {
        return [
            'hospital_id' => Hospital::factory(),
            'day_of_week' => $this->faker->randomElement(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']),
        ];
    }
}
