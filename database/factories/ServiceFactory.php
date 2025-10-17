<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition()
    {
        return [
            'service_name' =>'Service_' . $this->faker->unique()->numberBetween(1, 999999),
        ];
    }
}
