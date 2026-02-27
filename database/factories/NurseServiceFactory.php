<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NurseService>
 */
class NurseServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'nurse_id' => \App\Models\Nurse::factory(),
            'service_id' => Service::factory(['service_type' => 'nurse']),
            'price' => $this->faker->randomFloat(2, 10, 100),
        ];
    }
}
