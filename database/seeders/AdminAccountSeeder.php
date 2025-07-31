<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $admin = Account::firstOrCreate(
            ['email' => 'alaaalzoubi321@gmail.com'],
            [
                'password' => Hash::make('Admin@123'),
                'phone_number' => '0000000000',
            ]
        );

        $admin->assignRole('admin');

    }
}
