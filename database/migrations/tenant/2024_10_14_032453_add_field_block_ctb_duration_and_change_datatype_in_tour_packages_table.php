<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->integer('duration')->nullable(false)->change();
            $table->integer('day_hours')->nullable(false)->default(2)->change();
            $table->integer('block_ctb_duration')->default(0)->after('duration')->comment('Block Call To Book Duration');
            $table->text('ctb_description')->after('block_ctb_duration')->nullable()->comment('Call To Book Description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->integer('block_ctb_duration')->default(0)->after('duration')->comment('Block Call To Book Duration');
            $table->text('ctb_description')->after('block_ctb_duration')->nullable()->comment('Call To Book Description');
        });
    }
};
