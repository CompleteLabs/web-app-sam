<?php

namespace Database\Seeders;

use App\Models\Division;
use Illuminate\Database\Seeder;

class DivisionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $divisions = [
            ['id' => 1, 'badan_usaha_id' => 1, 'name' => 'MSIS'],
            ['id' => 2, 'badan_usaha_id' => 2, 'name' => 'ORAIMO'],
            ['id' => 3, 'badan_usaha_id' => 2, 'name' => '--'],
            ['id' => 4, 'badan_usaha_id' => 2, 'name' => 'REALME'],
            ['id' => 5, 'badan_usaha_id' => 3, 'name' => '-'],
            ['id' => 6, 'badan_usaha_id' => 2, 'name' => 'SPAREPARTDIST'],
            ['id' => 7, 'badan_usaha_id' => 4, 'name' => 'FASTEV'],
            ['id' => 8, 'badan_usaha_id' => 5, 'name' => 'ZTE'],
            ['id' => 9, 'badan_usaha_id' => 5, 'name' => 'SALES'],
            ['id' => 10, 'badan_usaha_id' => 5, 'name' => 'ITEL'],
            ['id' => 11, 'badan_usaha_id' => 5, 'name' => 'TECNO'],
        ];

        foreach ($divisions as $division) {
            Division::create($division);
        }
    }
}
