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
        Schema::create('gratuity_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('gratuity_name',255)->nullable();
            $table->enum('gratuity_type', ['fixed_percentage', 'fixed_amount', 'flexible'])->default('fixed_percentage');
            $table->json('gratuity_rules')->nullable();
            $table->decimal('gratuity_value', 10, 2)->nullable()->default(0.00);
            $table->boolean('pre_gratuity_enabled')->default(true);
            $table->boolean('post_gratuity_enabled')->default(true);
            $table->boolean('allow_pre_custom_gratuity')->default(false);
            $table->boolean('allow_post_custom_gratuity')->default(false);
            $table->decimal('min_gratuity', 10, 2)->nullable()->default(0.00);
            $table->decimal('max_gratuity', 10, 2)->nullable()->default(0.00);
            $table->decimal('default_gratuity', 10, 2)->nullable()->default(0.00);
            $table->boolean('is_mandatory')->default(false);
            $table->decimal('service_provider_cut', 5, 2)->nullable()->default(0.00);
            $table->enum('distribution_type', ['equal', 'percentage_based', 'custom'])->default('equal');
            $table->json('distribution_rules')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->dateTime('created_at')->useCurrent()->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index('created_by');
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Admin');
            $table->dateTime('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable()->index('updated_by');
            $table->enum('updated_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Admin');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gratuity_settings');
    }
};
