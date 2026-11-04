<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('addon_fields', 'display_once')) {
            try {
                Schema::table('addon_fields', function (Blueprint $table) {
                    $table->boolean('display_once')->default(false)->after('additional_fee')
                        ->comment('For price_per_pax fields: show once in the general add-ons section instead of once per traveler; charging/storage is unaffected.');
                });
            } catch (QueryException $e) {
                // 42S21 = duplicate column. Another process (concurrent tenant
                // migration run) won the race and already applied this change
                // since the check above ran.
                if ($e->getCode() !== '42S21') {
                    throw $e;
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('addon_fields', 'display_once')) {
            Schema::table('addon_fields', function (Blueprint $table) {
                $table->dropColumn('display_once');
            });
        }
    }
};
