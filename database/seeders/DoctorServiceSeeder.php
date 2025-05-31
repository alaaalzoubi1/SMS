<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DoctorService;

class DoctorServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DoctorService::create([
            'doctor_id' => 1,
            'name' => 'eyes',
            'price' => 150,
            'duration_minutes' => 30,
        ]);
    }
}
