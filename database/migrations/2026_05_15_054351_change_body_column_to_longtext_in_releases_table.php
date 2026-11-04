<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $type = DB::selectOne("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'releases' AND COLUMN_NAME = 'body'")?->DATA_TYPE;
        if ($type !== 'longtext') {
            Schema::table('releases', function (Blueprint $table) {
                $table->longText('body')->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->text('body')->change();
        });
    }
};
