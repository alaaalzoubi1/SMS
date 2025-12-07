<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProvinceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $now = Carbon::now()->toDateTimeString();

        $provinces = [
            ['name_ar' => 'دمشق',     'name_en' => 'Damascus'],
            ['name_ar' => 'ريف دمشق',  'name_en' => 'Rif Dimashq'],
            ['name_ar' => 'حلب',      'name_en' => 'Aleppo'],
            ['name_ar' => 'حمص',      'name_en' => 'Homs'],
            ['name_ar' => 'حماة',     'name_en' => 'Hama'],
            ['name_ar' => 'اللاذقية', 'name_en' => 'Latakia'],
            ['name_ar' => 'طرطوس',    'name_en' => 'Tartus'],
            ['name_ar' => 'إدلب',     'name_en' => 'Idlib'],
            ['name_ar' => 'دير الزور', 'name_en' => 'Deir ez-Zor'],
            ['name_ar' => 'الرقة',     'name_en' => 'Raqqa'],
            ['name_ar' => 'الحسكة',   'name_en' => 'Al-Hasakah'],
            ['name_ar' => 'السويداء', 'name_en' => 'As-Suwayda'],
            ['name_ar' => 'درعا',     'name_en' => 'Daraa'],
            ['name_ar' => 'القنيطرة', 'name_en' => 'Quneitra'],
        ];

        foreach ($provinces as $p) {
            DB::table('provinces')->insert(array_merge($p, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }
}
