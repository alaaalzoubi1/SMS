<?php

// database/factories/DoctorFactory.php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Account;
use App\Models\DoctorService;
use App\Enums\SpecializationType;
use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;
use MatanYadaev\EloquentSpatial\Objects\Point;

class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),  // Create an associated Account
            'province_id' => Province::inRandomOrder()->value('id'),
            'full_name' => $this->faker->name,
            'profile_description' => $this->faker->sentence,
            'address' => $this->faker->address,
            'age' => $this->faker->numberBetween(25, 65),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'location' => new Point($this->faker->latitude(), $this->faker->longitude() ),
            'specialization_id' => \App\Models\Specialization::inRandomOrder()->value('id') ?? 1,
            'license_image_path' => null,
        ];
    }

    /**
     * Add services to the doctor.
     *
     * @param int $count
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function withServices(int $count = 3): Factory
    {
        return $this->afterCreating(function (Doctor $doctor) use ($count) {
            // Create the services and associate them with the doctor
            DoctorService::factory()->count($count)->create([
                'doctor_id' => $doctor->id,
            ]);
        });
    }
    public function configure(): DoctorFactory
    {
        return $this->afterCreating(function (Doctor $doctor) {
            $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'];
            foreach ($days as $day) {
                $doctor->doctorWorkSchedule()->create([
                    'day_of_week' => $day,
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                ]);
            }
        });
    }
}
