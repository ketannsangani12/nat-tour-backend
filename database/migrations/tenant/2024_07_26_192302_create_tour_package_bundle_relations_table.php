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
        if(!Schema::hasTable('tour_package_bundle_relations')) {
            Schema::create('tour_package_bundle_relations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('parent_id')->index('parent_id')->nullable()->index('tour_package_bundle_relations_parent_id_foreign');
                $table->unsignedBigInteger('tour_package_id')->index('tour_package_id')->nullable()->index('tour_package_bundle_relations_tour_package_id_foreign');
                $table->string('package_name')->nullable();
                $table->dateTime('created_at')->useCurrent()->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable()->index('created_by');
                $table->enum('created_by_user_type', ['Admin', 'Tour Operator'])->default('Tour Operator');
                $table->dateTime('updated_at')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable()->index('updated_by');
                $table->enum('updated_by_user_type', ['Admin', 'Tour Operator'])->default('Tour Operator');
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable()->default(0)->index('deleted_by');
                $table->enum('deleted_by_user_type', ['Admin', 'Tour Operator'])->default('Tour Operator');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_package_bundle_relations');
    }
};
