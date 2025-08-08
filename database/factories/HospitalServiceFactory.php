<?php
// database/factories/HospitalServiceFactory.php

namespace Database\Factories;

use App\Models\HospitalService;
use App\Models\Hospital;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class HospitalServiceFactory extends Factory
{
    protected $model = HospitalService::class;

    public function definition()
    {
        return [
            'hospital_id' => Hospital::factory(),
            'service_id' => Service::factory(),
            'price' => $this->faker->randomFloat(2, 50, 500),  // Price between 50 and 500
            'capacity' => $this->faker->numberBetween(1, 100), // Capacity between 1 and 100
        ];
    }
}
