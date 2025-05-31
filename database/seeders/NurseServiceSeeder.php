<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\NurseService;

class NurseServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        NurseService::create([
            'nurse_id' => 1,
            'name' => 'Home',
            'price' => 25.00,
        ]);
    }
}
