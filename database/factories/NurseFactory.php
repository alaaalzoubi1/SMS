<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\NurseService;
use App\Models\NurseSubservice;
use Illuminate\Database\Eloquent\Factories\Factory;
use TarfinLabs\LaravelSpatial\Types\Point;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Nurse>
 */
class NurseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'account_id' => Account::factory(),
            'full_name' => $this->faker->name,
            'address' => $this->faker->address,
            'graduation_type' => $this->faker->randomElement(['معهد', 'مدرسة', 'جامعة', 'ماجستير', 'دكتوراه']),
            'location' => new Point($this->faker->numberBetween(-180,180),$this->faker->numberBetween(-90,90),4326 ),
            'age' => $this->faker->numberBetween(25, 65),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'profile_description' => $this->faker->sentence,
            'license_image_path' => '',
        ];
    }

    // Create nurse with services and subservices
    public function withServicesAndSubservices(int $servicesCount = 2, int $subservicesCount = 3): NurseFactory
    {
        return $this->has(
            NurseService::factory()
                ->count($servicesCount)
                ->has(
                    NurseSubservice::factory()
                        ->count($subservicesCount)
                ,'subservices'), 'services');
    }
}
