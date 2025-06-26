<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $regions = [
            ['id' => 1, 'badan_usaha_id' => 1, 'division_id' => 1, 'name' => 'SWJ'],
            ['id' => 3, 'badan_usaha_id' => 1, 'division_id' => 1, 'name' => 'NWJ'],
            ['id' => 5, 'badan_usaha_id' => 2, 'division_id' => 3, 'name' => 'NWJ'],
            ['id' => 6, 'badan_usaha_id' => 1, 'division_id' => 1, 'name' => 'NCJ'],
            ['id' => 8, 'badan_usaha_id' => 2, 'division_id' => 3, 'name' => 'NCJ'],
            ['id' => 9, 'badan_usaha_id' => 1, 'division_id' => 1, 'name' => 'SCJ'],
            ['id' => 11, 'badan_usaha_id' => 2, 'division_id' => 3, 'name' => 'SCJ'],
            ['id' => 13, 'badan_usaha_id' => 2, 'division_id' => 4, 'name' => 'BIGCIREBON'],
            ['id' => 14, 'badan_usaha_id' => 2, 'division_id' => 4, 'name' => 'BIGTEGAL'],
            ['id' => 16, 'badan_usaha_id' => 2, 'division_id' => 4, 'name' => 'BIGSEMARANG'],
            ['id' => 17, 'badan_usaha_id' => 3, 'division_id' => 5, 'name' => '-'],
            ['id' => 18, 'badan_usaha_id' => 1, 'division_id' => 1, 'name' => 'JABO'],
            ['id' => 20, 'badan_usaha_id' => 2, 'division_id' => 3, 'name' => 'JABO'],
            ['id' => 21, 'badan_usaha_id' => 2, 'division_id' => 4, 'name' => 'BIGSOLO'],
            ['id' => 22, 'badan_usaha_id' => 2, 'division_id' => 4, 'name' => 'BIGJOGJA'],
            ['id' => 23, 'badan_usaha_id' => 2, 'division_id' => 4, 'name' => 'BIGBANDUNG'],
            ['id' => 24, 'badan_usaha_id' => 2, 'division_id' => 4, 'name' => 'BIGKARAWANG'],
            ['id' => 26, 'badan_usaha_id' => 2, 'division_id' => 4, 'name' => 'BIGPURWOKERTO'],
            ['id' => 27, 'badan_usaha_id' => 2, 'division_id' => 4, 'name' => 'BIGTASIK'],
            ['id' => 28, 'badan_usaha_id' => 2, 'division_id' => 2, 'name' => 'JABO1'],
            ['id' => 29, 'badan_usaha_id' => 2, 'division_id' => 2, 'name' => 'JABO2'],
            ['id' => 30, 'badan_usaha_id' => 2, 'division_id' => 2, 'name' => 'JABAR2'],
            ['id' => 31, 'badan_usaha_id' => 2, 'division_id' => 2, 'name' => 'JABAR1'],
            ['id' => 32, 'badan_usaha_id' => 2, 'division_id' => 2, 'name' => 'JATENG'],
            ['id' => 33, 'badan_usaha_id' => 2, 'division_id' => 2, 'name' => 'JATIM'],
            ['id' => 34, 'badan_usaha_id' => 2, 'division_id' => 2, 'name' => 'BALI'],
            ['id' => 41, 'badan_usaha_id' => 2, 'division_id' => 2, 'name' => 'JABAR3'],
            ['id' => 42, 'badan_usaha_id' => 2, 'division_id' => 2, 'name' => 'LOMBOK'],
            ['id' => 43, 'badan_usaha_id' => 2, 'division_id' => 6, 'name' => 'JABAR1'],
            ['id' => 44, 'badan_usaha_id' => 2, 'division_id' => 6, 'name' => 'JABAR2'],
            ['id' => 45, 'badan_usaha_id' => 2, 'division_id' => 6, 'name' => 'JABAR3'],
            ['id' => 46, 'badan_usaha_id' => 2, 'division_id' => 6, 'name' => 'JABAR4'],
            ['id' => 47, 'badan_usaha_id' => 2, 'division_id' => 6, 'name' => 'JABO1'],
            ['id' => 48, 'badan_usaha_id' => 2, 'division_id' => 6, 'name' => 'JABO2'],
            ['id' => 49, 'badan_usaha_id' => 2, 'division_id' => 2, 'name' => 'NTT'],
            ['id' => 50, 'badan_usaha_id' => 4, 'division_id' => 7, 'name' => 'JABAR'],
            ['id' => 51, 'badan_usaha_id' => 4, 'division_id' => 7, 'name' => 'JABO'],
            ['id' => 52, 'badan_usaha_id' => 4, 'division_id' => 7, 'name' => 'JATENG'],
            ['id' => 54, 'badan_usaha_id' => 4, 'division_id' => 7, 'name' => 'JATIM'],
            ['id' => 55, 'badan_usaha_id' => 4, 'division_id' => 7, 'name' => 'SUMATERA'],
            ['id' => 56, 'badan_usaha_id' => 4, 'division_id' => 7, 'name' => 'BALINUSA'],
            ['id' => 57, 'badan_usaha_id' => 4, 'division_id' => 7, 'name' => 'KALIMANTAN'],
            ['id' => 58, 'badan_usaha_id' => 4, 'division_id' => 7, 'name' => 'SULAWESI'],
            ['id' => 59, 'badan_usaha_id' => 4, 'division_id' => 7, 'name' => 'PAPUA'],
            ['id' => 60, 'badan_usaha_id' => 4, 'division_id' => 7, 'name' => 'TANGERANGBANTEN'],
            ['id' => 61, 'badan_usaha_id' => 2, 'division_id' => 2, 'name' => 'TANGERANGBANTEN'],
            ['id' => 62, 'badan_usaha_id' => 2, 'division_id' => 2, 'name' => 'JATIM2'],
            ['id' => 63, 'badan_usaha_id' => 5, 'division_id' => 8, 'name' => 'BIGSOLO'],
            ['id' => 64, 'badan_usaha_id' => 5, 'division_id' => 8, 'name' => 'BIGSEMARANG'],
            ['id' => 66, 'badan_usaha_id' => 5, 'division_id' => 8, 'name' => 'BIGPURWOKERTO'],
            ['id' => 67, 'badan_usaha_id' => 5, 'division_id' => 8, 'name' => 'BIGJOGJA'],
            ['id' => 68, 'badan_usaha_id' => 5, 'division_id' => 8, 'name' => 'BIGTEGAL'],
            ['id' => 69, 'badan_usaha_id' => 5, 'division_id' => 9, 'name' => 'JAWATIMUR'],
            ['id' => 70, 'badan_usaha_id' => 5, 'division_id' => 9, 'name' => 'SUMATERA'],
            ['id' => 71, 'badan_usaha_id' => 5, 'division_id' => 9, 'name' => 'RIAU'],
            ['id' => 73, 'badan_usaha_id' => 5, 'division_id' => 9, 'name' => 'SUMBAR'],
            ['id' => 74, 'badan_usaha_id' => 5, 'division_id' => 9, 'name' => 'SUMSEL'],
            ['id' => 75, 'badan_usaha_id' => 5, 'division_id' => 9, 'name' => 'BKL'],
            ['id' => 76, 'badan_usaha_id' => 5, 'division_id' => 9, 'name' => 'BANGKABELITUNG'],
            ['id' => 77, 'badan_usaha_id' => 5, 'division_id' => 8, 'name' => 'BIGBEKASI'],
            ['id' => 78, 'badan_usaha_id' => 5, 'division_id' => 8, 'name' => 'JATIM1'],
            ['id' => 79, 'badan_usaha_id' => 5, 'division_id' => 8, 'name' => 'JATIM2'],
            ['id' => 80, 'badan_usaha_id' => 5, 'division_id' => 8, 'name' => 'JATIM3'],
            ['id' => 81, 'badan_usaha_id' => 5, 'division_id' => 8, 'name' => 'JATIM4'],
            ['id' => 82, 'badan_usaha_id' => 5, 'division_id' => 11, 'name' => 'ACEH'],
            ['id' => 83, 'badan_usaha_id' => 5, 'division_id' => 11, 'name' => 'BANGKA BELITUNG'],
            ['id' => 85, 'badan_usaha_id' => 5, 'division_id' => 11, 'name' => 'BENGKULU'],
            ['id' => 86, 'badan_usaha_id' => 5, 'division_id' => 11, 'name' => 'JAMBI'],
            ['id' => 87, 'badan_usaha_id' => 5, 'division_id' => 11, 'name' => 'LAMPUNG'],
            ['id' => 88, 'badan_usaha_id' => 5, 'division_id' => 11, 'name' => 'RIAU'],
            ['id' => 89, 'badan_usaha_id' => 5, 'division_id' => 11, 'name' => 'SUMATERA BARAT'],
            ['id' => 93, 'badan_usaha_id' => 5, 'division_id' => 11, 'name' => 'SUMATERA SELATAN'],
            ['id' => 99, 'badan_usaha_id' => 5, 'division_id' => 11, 'name' => 'SUMATERAUTARA'],
            ['id' => 109, 'badan_usaha_id' => 5, 'division_id' => 8, 'name' => 'JABO'],
            ['id' => 110, 'badan_usaha_id' => 5, 'division_id' => 8, 'name' => 'JABAR1'],
            ['id' => 111, 'badan_usaha_id' => 5, 'division_id' => 8, 'name' => 'JABAR2'],
            ['id' => 112, 'badan_usaha_id' => 5, 'division_id' => 8, 'name' => 'JABAR3'],
            ['id' => 113, 'badan_usaha_id' => 5, 'division_id' => 8, 'name' => 'JABAR4'],
            ['id' => 114, 'badan_usaha_id' => 5, 'division_id' => 8, 'name' => 'JABAR5'],
            ['id' => 115, 'badan_usaha_id' => 5, 'division_id' => 8, 'name' => 'JABO-ZTE'],
            ['id' => 4498, 'badan_usaha_id' => 5, 'division_id' => 11, 'name' => 'KEPULAUAN RIAU'],
            ['id' => 4500, 'badan_usaha_id' => 5, 'division_id' => 11, 'name' => '-'],
        ];

        foreach ($regions as $region) {
            Region::create($region);
        }
    }
}
