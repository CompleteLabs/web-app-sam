<?php

namespace Database\Seeders;

use App\Models\BadanUsaha;
use Illuminate\Database\Seeder;

class BadanUsahaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $badanUsahas = [
            ['id' => 1, 'name' => 'PT.MSI'],
            ['id' => 2, 'name' => 'CV.TOP'],
            ['id' => 3, 'name' => '-'],
            ['id' => 4, 'name' => 'PT.MKLI'],
            ['id' => 5, 'name' => 'CV.MAJU'],
        ];

        foreach ($badanUsahas as $badanUsaha) {
            BadanUsaha::create($badanUsaha);
        }
    }
}
