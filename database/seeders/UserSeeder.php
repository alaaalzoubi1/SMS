<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'account_id' => 1,
            'birthdate' => now()->subYears(22)->format('Y-m-d'),
            'gender' => 'male',
        ]);
    }
}
