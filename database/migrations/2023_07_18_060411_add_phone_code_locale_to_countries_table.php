<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            if (!Schema::hasColumn('countries', 'code')) {
                $table->string('code', 10)->nullable()->after('name');
            }
            if (!Schema::hasColumn('countries', 'currency')) {
                $table->string('currency', 10)->nullable()->after('code');
            }
            if (!Schema::hasColumn('countries', 'currency_symbol')) {
                $table->string('currency_symbol', 5)->nullable()->after('currency');
            }
            if (!Schema::hasColumn('countries', 'phone_code')) {
                $table->string('phone_code', 10)->nullable()->after('currency_symbol');
            }
            if (!Schema::hasColumn('countries', 'locale')) {
                $table->string('locale', 20)->nullable()->after('phone_code');
            }
        });

        $data = [
            1 => ['code' => 'US', 'currency' => 'USD', 'currency_symbol' => '$',   'phone_code' => '1', 'locale' => 'en-US'],
            2 => ['code' => 'CA', 'currency' => 'CAD', 'currency_symbol' => 'CA$', 'phone_code' => '1', 'locale' => 'en-CA'],
        ];

        foreach ($data as $id => $values) {
            DB::table('countries')->where('id', $id)->update($values);
        }
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn(['code', 'currency', 'currency_symbol', 'phone_code', 'locale']);
        });
    }
};
