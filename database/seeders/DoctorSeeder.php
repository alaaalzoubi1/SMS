<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Doctor;

class DoctorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Doctor::create([
            'account_id' => 2,
            'specialization' => 'Cardiology',
            'address' => 'forqan',
            'age' => 45,
            'gender' => 'male',
        ]);
    }
}
