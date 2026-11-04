<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tour_slot_summaries', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tour_slot_id')->nullable()->index();
            $table->unsignedBigInteger('tour_package_id')->nullable()->index(); // null if group row
            $table->unsignedBigInteger('group_id')->nullable()->index(); // null if package row

            $table->date('date');
            $table->time('time_from')->nullable();
            $table->time('time_to')->nullable();

            $table->unsignedInteger('total_seats')->default(0);
            $table->unsignedInteger('booked_tourists')->default(0);
            $table->unsignedInteger('available_seats')->default(0);
            $table->unsignedInteger('overbooked_count')->default(0);

            $table->timestamps();

            // Ensure uniqueness
            $table->unique(['tour_slot_id', 'tour_package_id', 'group_id'], 'slot_pkg_group_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_slot_summaries');
    }
};
