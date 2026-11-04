<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tour_slot_bulk_operations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tour_package_id')->nullable()->index();

            $table->enum('action', ['status', 'seats', 'convert', 'visibility', 'repeat', 'delete', 'create']);

            $table->date('from_date');
            $table->date('to_date');

            $table->longText('slots')->nullable();    // selected from-times
            $table->longText('payload')->nullable();  // per-slot seats/status, convert mappings, repeat rows, etc.

            $table->unsignedInteger('total_slots')->default(0);

            $table->enum('status', ['queued', 'applied', 'failed'])->default('queued')->index();
            $table->dateTime('applied_at')->nullable();

            $table->dateTime('created_at')->useCurrent()->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->default(NULL)->index();
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Affiliate'])->default('Tour Operator');

            $table->unsignedBigInteger('updated_by')->nullable()->default(NULL)->index();
            $table->enum('updated_by_user_type', ['Admin', 'Tour Operator', 'Affiliate'])->default('Tour Operator');

            $table->unsignedBigInteger('deleted_by')->default(0)->index();
            $table->dateTime('deleted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_slot_bulk_operations');
    }
};
