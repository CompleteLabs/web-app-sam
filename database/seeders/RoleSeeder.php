<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Role::insert([
            [
                'id' => 1,
                'name' => 'ASM',
                'parent_id' => null,
                'can_access_web' => 1,
                'can_access_mobile' => 1,
                'scope_required_fields' => '["badan_usaha_id", "division_id"]',
                'scope_multiple_fields' => '[]',
            ],
            [
                'id' => 2,
                'name' => 'ASC',
                'parent_id' => 1,
                'can_access_web' => 1,
                'can_access_mobile' => 1,
                'scope_required_fields' => '["badan_usaha_id", "division_id", "region_id"]',
                'scope_multiple_fields' => '[]',
            ],
            [
                'id' => 3,
                'name' => 'DSF/DM',
                'parent_id' => 2,
                'can_access_web' => 1,
                'can_access_mobile' => 1,
                'scope_required_fields' => '["badan_usaha_id", "division_id", "region_id", "cluster_id"]',
                'scope_multiple_fields' => '["cluster_id"]',
            ],
            [
                'id' => 4,
                'name' => 'ADMIN',
                'parent_id' => null,
                'can_access_web' => 1,
                'can_access_mobile' => 1,
                'scope_required_fields' => null,
                'scope_multiple_fields' => null,
            ],
        ]);
    }
}
