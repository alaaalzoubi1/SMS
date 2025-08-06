<?php

namespace Database\Factories;

use App\Models\NurseSubservice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NurseSubsercvice>
 */
class NurseSubserviceFactory extends Factory
{
    protected $model = NurseSubservice::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    public function definition(): array
    {
        return [
            'service_id' => \App\Models\NurseService::factory(),
            'name' => $this->faker->word,
            'price' => $this->faker->randomFloat(2, 5, 50),
        ];
    }
}
