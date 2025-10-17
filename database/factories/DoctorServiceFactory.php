<?php

namespace Database\Factories;
// database/factories/DoctorServiceFactory.php

namespace Database\Factories;

use App\Models\DoctorService;
use Illuminate\Database\Eloquent\Factories\Factory;

class DoctorServiceFactory extends Factory
{
    protected $model = DoctorService::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->lexify('Service ???'),
            'price' => $this->faker->randomFloat(2, 50, 500),  // Random price between 50 and 500
            'duration_minutes' => $this->faker->numberBetween(15, 60),  // Random duration between 15 and 60 minutes
        ];
    }
}

