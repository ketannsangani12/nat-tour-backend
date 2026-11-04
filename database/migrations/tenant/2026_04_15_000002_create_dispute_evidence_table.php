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
        Schema::create('dispute_evidence', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('dispute_id')->index();
            $table->string('evidence_type', 50)->index();
            $table->string('file_path', 500)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('content_type', 100)->nullable();
            $table->string('stripe_file_id', 255)->nullable()->index();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->unsignedBigInteger('created_by')->index();
            $table->enum('created_by_user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate'])->default('Tour Operator');
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('dispute_id')->references('id')->on('disputes')->onDelete('cascade');
            
            // Composite indexes for common queries
            $table->index(['dispute_id', 'evidence_type']);
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
        Schema::dropIfExists('dispute_evidence');
    }
};