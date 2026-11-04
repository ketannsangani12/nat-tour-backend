<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class AssignRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();

        DB::table('model_has_roles')->insert([
            [ // Admin Role Assignment
                'role_id' => 3,
                'model_type' => 'App\Models\Admin',
                'model_id' => 1,
            ],
            [
                'role_id' => 3,
                'model_type' => 'App\Models\Admin',
                'model_id' => 2,
            ],
            [
                'role_id' => 3,
                'model_type' => 'App\Models\Admin',
                'model_id' => 3,
            ],
            [
                'role_id' => 3,
                'model_type' => 'App\Models\Admin',
                'model_id' => 4,
            ],
            [
                'role_id' => 3,
                'model_type' => 'App\Models\Admin',
                'model_id' => 5,
            ],
            [
                'role_id' => 3,
                'model_type' => 'App\Models\Admin',
                'model_id' => 6,
            ],
            [
                'role_id' => 3,
                'model_type' => 'App\Models\Admin',
                'model_id' => 7,
            ], // Staff Role Assignment
            [
                'role_id' => 1,
                'model_type' => 'App\Models\Staff',
                'model_id' => 1,
            ],
            [
                'role_id' => 1,
                'model_type' => 'App\Models\Staff',
                'model_id' => 2,
            ],
            [
                'role_id' => 1,
                'model_type' => 'App\Models\Staff',
                'model_id' => 3,
            ],
            [
                'role_id' => 1,
                'model_type' => 'App\Models\Staff',
                'model_id' => 4,
            ],
            [
                'role_id' => 1,
                'model_type' => 'App\Models\Staff',
                'model_id' => 5,
            ],
            [
                'role_id' => 1,
                'model_type' => 'App\Models\Staff',
                'model_id' => 6,
            ],
            [
                'role_id' => 1,
                'model_type' => 'App\Models\Staff',
                'model_id' => 7,
            ],
            [
                'role_id' => 1,
                'model_type' => 'App\Models\Staff',
                'model_id' => 8,
            ],
            [
                'role_id' => 1,
                'model_type' => 'App\Models\Staff',
                'model_id' => 9,
            ]
        ]);

        DB::commit();
    }
}
