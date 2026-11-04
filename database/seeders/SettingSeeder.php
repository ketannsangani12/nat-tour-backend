<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
      DB::table('settings')->insert([
        'key' => 'API_VERSION',
        'value' => '1.5',
      ]);
      DB::table('settings')->insert([
        'key' => 'IS_NO_HIDE',
        'value' => 1,
      ]);

      DB::table('settings')->insert([
        'key' => 'CUSTOM_FIELDS',
        'value' => 0,
      ]);
    }
}
