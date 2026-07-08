<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@rosales.gov.ph'],
            [
                'name' => 'MDRRMO Rosales Admin',
                'password' => Hash::make('rosalesadmin'),
            ]
        );
    }
}