<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Create default admin user
        User::updateOrCreate(
            ['email' => 'admin@foundation.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'is_admin' => true,
            ]
        );

        // Create test admin user
        User::updateOrCreate(
            ['email' => 'test@namafoundation.org'],
            [
                'name' => 'Test Admin',
                'password' => Hash::make('password'),
                'is_admin' => true,
            ]
        );
    }
}
