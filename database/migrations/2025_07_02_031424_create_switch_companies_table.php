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
        Schema::create('switch_companies', function (Blueprint $table) {
             $table->id();

            $table->unsignedBigInteger('network_id');
            $table->Integer('group_id');
            $table->string('tenant_id');
            $table->Integer('user_id');

            $table->enum('user_type', ['Admin', 'Tour Operator', 'Affiliate'])->default('Admin');

            $table->timestamps();

            // Foreign key constraints
            $table->foreign('network_id')->references('id')->on('networks')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('switch_companies');
    }
};
