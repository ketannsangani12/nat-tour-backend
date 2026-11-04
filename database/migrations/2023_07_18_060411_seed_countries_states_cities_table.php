<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('countries')->exists()) {
            DB::table('countries')->insert([
                [
                    'id'                   => 1,
                    'name'                 => 'United States',
                    'code'                 => 'US',
                    'currency'             => 'USD',
                    'currency_symbol'      => '$',
                    'phone_code'           => '1',
                    'locale'               => 'en-US',
                    'status'               => 'Active',
                    'created_at'           => '2026-05-15 15:01:07',
                    'created_by'           => 1,
                    'created_by_user_type' => 'Admin',
                    'updated_at'           => null,
                    'updated_by'           => null,
                    'updated_by_user_type' => 'Admin',
                    'deleted_at'           => null,
                    'deleted_by'           => 0,
                    'deleted_by_user_type' => null,
                ],
                [
                    'id'                   => 2,
                    'name'                 => 'Canada',
                    'code'                 => 'CA',
                    'currency'             => 'CAD',
                    'currency_symbol'      => 'CA$',
                    'phone_code'           => '1',
                    'locale'               => 'en-CA',
                    'status'               => 'Active',
                    'created_at'           => '2026-05-15 15:01:07',
                    'created_by'           => 1,
                    'created_by_user_type' => 'Admin',
                    'updated_at'           => null,
                    'updated_by'           => null,
                    'updated_by_user_type' => 'Admin',
                    'deleted_at'           => null,
                    'deleted_by'           => 0,
                    'deleted_by_user_type' => null,
                ],
            ]);
        }

        if (!DB::table('states')->exists()) {
            DB::table('states')->insert([
                'id'                   => 1,
                'country_id'           => 1,
                'name'                 => 'Arizona',
                'permit_fee'           => 8.00,
                'status'               => 'Active',
                'created_at'           => '2026-05-15 15:01:07',
                'created_by'           => 1,
                'created_by_user_type' => 'Admin',
                'updated_at'           => null,
                'updated_by'           => null,
                'updated_by_user_type' => 'Admin',
                'deleted_at'           => null,
                'deleted_by'           => 0,
                'deleted_by_user_type' => null,
            ]);
        }

        if (!DB::table('cities')->exists()) {
            DB::table('cities')->insert([
                'id'                   => 1,
                'state_id'             => 1,
                'name'                 => 'Page',
                'timezone'             => 'America/Phoenix',
                'status'               => 'Active',
                'created_at'           => '2025-09-02 14:24:36',
                'created_by'           => 7,
                'created_by_user_type' => 'Admin',
                'updated_at'           => '2025-09-30 11:05:59',
                'updated_by'           => 7,
                'updated_by_user_type' => 'Admin',
                'deleted_at'           => null,
                'deleted_by'           => 0,
                'deleted_by_user_type' => null,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('cities')->whereIn('id', [1])->delete();
        DB::table('states')->whereIn('id', [1])->delete();
        DB::table('countries')->whereIn('id', [1, 2])->delete();
    }
};
