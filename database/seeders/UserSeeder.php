<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Admin User
        User::firstOrCreate([
            'email' => 'admin@gmail.com',
        ], [
            'id' => 691,
            'name' => 'APP DEVELOPER',
            'username' => 'appdev',
            'password' => Hash::make('password'),
            'phone' => '628123456789',
            'role_id' => 13,
        ]);
    }
}
