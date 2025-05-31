<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\NurseSubservice;

class NurseSubsercviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        NurseSubservice::create([
            'service_id' => 1,
            'name' => 'Shoulder Injection',
            'price' => 10,
        ]);
    }
}
