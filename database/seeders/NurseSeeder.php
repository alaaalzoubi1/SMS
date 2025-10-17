<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Nurse;

class NurseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Nurse::factory()
            ->count(100)
            ->withServicesAndSubservices()  // Using the custom method to include services and subservices
            ->create();
    }
}
