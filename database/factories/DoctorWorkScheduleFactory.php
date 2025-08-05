<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DoctorWorkSchedule>
 */
class DoctorWorkScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $day = $this->faker->unique()->randomElement([
            'saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'
        ]);

        return [
            'day_of_week' => $day,
            'start_time' => $this->faker->time('H:i', '08:00'),
            'end_time' => $this->faker->time('H:i', '17:00'),
        ];
    }
}
