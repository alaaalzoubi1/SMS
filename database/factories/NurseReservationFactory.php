<?php

namespace Database\Factories;

use App\Models\Nurse;
use App\Models\NurseService;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NurseReservation>
 */
class NurseReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['pending', 'accepted', 'completed', 'rejected', 'cancelled'];

        return [
            'user_id' => User::factory(),
            'nurse_id' => Nurse::factory(),
            'nurse_service_id' => NurseService::factory(),
            'reservation_type' => $this->faker->randomElement(['direct', 'manual']),
            'status' => $this->faker->randomElement($statuses),
            'price' => $this->faker->numberBetween(50, 300),
            'note' => $this->faker->sentence(),
            'location' => new Point($this->faker->latitude,$this->faker->longitude),
            'start_at' => now()->subDays(rand(0, 365)),
            'end_at' => now()->subDays(rand(0, 365))->addHour(),
        ];
    }
}
