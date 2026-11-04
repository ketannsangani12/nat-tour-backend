<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        if (Schema::hasTable('sent_emails')) {
            return;
        }

        Schema::create('sent_emails', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('tour_booking_id')->nullable();
            $table->integer('email_type')->nullable();
            $table->char('hash', 32)->unique();
            $table->text('headers')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('sender_email')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('subject')->nullable();
            $table->text('content')->nullable();
            $table->integer('opens')->nullable();
            $table->integer('clicks')->nullable();
            $table->timestamps();
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('clicked_at')->nullable();
            $table->string('message_id')->nullable()->index();
            $table->text('meta')->nullable();

            $table->index(['tour_booking_id', 'email_type'], 'idx_sent_emails_booking_type');
            $table->index('created_at', 'idx_sent_emails_created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sent_emails', function (Blueprint $table) {
            $table->dropIndex('idx_sent_emails_booking_type');
            $table->dropIndex('idx_sent_emails_created_at');
        });
        Schema::dropIfExists('sent_emails');
    }
};
