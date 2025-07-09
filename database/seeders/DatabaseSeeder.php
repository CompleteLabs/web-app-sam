<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed the organizational structure in order
        $this->call([
            // BadanUsahaSeeder::class,
            // DivisionSeeder::class,
            // RegionSeeder::class,
            // ClusterSeeder::class,
            RoleSeeder::class,
            DynamicPermissionSeeder::class,
            UserSeeder::class,
        ]);
    }
}
