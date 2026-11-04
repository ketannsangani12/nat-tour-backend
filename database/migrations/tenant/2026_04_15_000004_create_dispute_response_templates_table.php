<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dispute_response_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->string('dispute_reason', 50)->index();
            $table->text('template_content');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->index();
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator'])->default('Tour Operator');
            $table->timestamps();
            
            // Composite indexes for common queries
            $table->index(['dispute_reason', 'is_active']);
            $table->index(['created_by', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dispute_response_templates');
    }
};