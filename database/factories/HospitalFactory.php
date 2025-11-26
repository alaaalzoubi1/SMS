<?php
// database/factories/HospitalFactory.php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use MatanYadaev\EloquentSpatial\Objects\Point;

class HospitalFactory extends Factory
{
    protected $model = Hospital::class;

    public function definition()
    {
        return [
            'account_id' => Account::factory(), // Assuming Account factory exists
            'full_name' => $this->faker->company,
            'unique_code' => Str::uuid(),
            'address' => $this->faker->address,
            'location' => new Point($this->faker->latitude(), $this->faker->longitude() ),
        ];
    }
}
