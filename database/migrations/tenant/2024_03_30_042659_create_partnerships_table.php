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
        Schema::create('partnerships', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('tenant_name');
            $table->integer('current_user_id');
            $table->integer('partner_user_id');
            $table->enum('current_user_type', ['Admin', 'Tour Operator', 'Affiliate', 'Customer'])->default('Admin');
            $table->enum('partner_user_type', ['Admin', 'Tour Operator', 'Affiliate', 'Customer'])->default('Admin');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partnerships');
    }
};
