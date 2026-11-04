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
        Schema::create('dispute_timeline', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('dispute_id')->index();
            $table->string('event_type', 50)->index();
            $table->json('event_data')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->enum('user_type', ['Admin', 'Tour Operator', 'Customer', 'Affiliate', 'System'])->default('System');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            
            // Foreign key constraints
            $table->foreign('dispute_id')->references('id')->on('disputes')->onDelete('cascade');
            
            // Composite indexes for common queries
            $table->index(['dispute_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dispute_timeline');
    }
};