<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Specialization;

class SpecializationSeeder extends Seeder
{
    public function run(): void
    {
        $specializations = [
            [
                'name_en' => 'General Medicine',
                'name_ar' => 'طب عام',
                'image'   => 'specializations/general.png',
            ],
            [
                'name_en' => 'Dentistry',
                'name_ar' => 'طب أسنان',
                'image'   => 'specializations/dentistry.png',
            ],
            [
                'name_en' => 'Dermatology',
                'name_ar' => 'جلدية',
                'image'   => 'specializations/dermatology.png',
            ],
            [
                'name_en' => 'Pediatrics',
                'name_ar' => 'طب أطفال',
                'image'   => 'specializations/pediatrics.png',
            ],
            [
                'name_en' => 'Ophthalmology',
                'name_ar' => 'طب عيون',
                'image'   => 'specializations/ophthalmology.png',
            ],
        ];

        foreach ($specializations as $specialization) {
            Specialization::create($specialization);
        }
    }
}
