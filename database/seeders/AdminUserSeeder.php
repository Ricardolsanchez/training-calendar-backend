<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@alonsoalonsolaw.com'],
            [
                'name' => 'admin',
                'password' => Hash::make('1234'),
                'is_admin' => true,
            ]
        );
    }
}