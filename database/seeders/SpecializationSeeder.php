<?php

namespace Database\Seeders;

use App\Models\Specialization;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SpecializationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Specialization::insert([
            ['id' => 1, 'name' => 'طب عام'],
            ['id' => 2, 'name' => 'طب أسنان'],
            ['id' => 3, 'name' => 'جلدية'],
        ]);
    }
}
