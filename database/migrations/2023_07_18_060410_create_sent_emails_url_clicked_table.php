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
        if (Schema::hasTable('sent_emails_url_clicked')) {
            return;
        }

        Schema::create('sent_emails_url_clicked', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('sent_email_id')->index('sent_emails_url_clicked_sent_email_id_foreign');
            $table->text('url')->nullable();
            $table->char('hash', 32);
            $table->integer('clicks')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sent_emails_url_clicked');
    }
};
