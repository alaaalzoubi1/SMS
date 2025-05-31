<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Account::insert([
            [
                'full_name' => 'abdallah dadikhy',
                'email' => 'abdallahdadikhy@gmail.com',
                'password' => Hash::make('123123123'),
                'phone_number' => '0937808581',
                'fcm_token' => null,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'alaa zoubi',
                'email' => 'alaazoubi@gmail.com',
                'password' => Hash::make('123123123'),
                'phone_number' => '0987654321',
                'fcm_token' => null,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'fatima',
                'email' => 'fatima@gmail.com',
                'password' => Hash::make('123123123'),
                'phone_number' => '0987654321',
                'fcm_token' => null,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'syrian hospital',
                'email' => 'syrianhospital@gmail.com',
                'password' => Hash::make('123123123'),
                'phone_number' => '0987654321',
                'fcm_token' => null,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
