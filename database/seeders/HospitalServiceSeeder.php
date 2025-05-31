<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\HospitalService;

class HospitalServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        HospitalService::create([
            'hospital_id' => 1,
            'service_id' => 1,
            'price' => 200,
            'capacity' => 30,
        ]);
    }
}
