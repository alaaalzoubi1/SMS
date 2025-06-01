<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $this->call([
            AccountSeeder::class,
            NurseSeeder::class,
            DoctorSeeder::class,
            HospitalSeeder::class,
            UserSeeder::class,
            ServiceSeeder::class,
            NurseServiceSeeder::class,
            NurseReservationSeeder::class,
            NurseSubsercviceSeeder::class,
            HospitalServiceSeeder::class,
            HospitalWorkScheduleSeeder::class,
            HospitalServiceReservationSeeder::class,
            DoctorServiceSeeder::class,
            DoctorWorkScheduleSeeder::class,
            DoctorReservationSeeder::class,
            RolesSeeder::class,
        ]);

    }
}
