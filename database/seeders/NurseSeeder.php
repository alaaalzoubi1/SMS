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
        Nurse::create([
            'account_id' => 3,
            'specialization' => 'Pediatrics',
            'study_stage' => 'Bachelor',
            'longitude' => 36,
            'latitude' => 33,
            'age' => 28,
            'gender' => 'female',
        ]);
    }
}
