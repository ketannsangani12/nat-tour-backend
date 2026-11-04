<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // MySQL error 1050: table already exists. Guards a concurrent tenant migration run that already created it.
    private const TABLE_ALREADY_EXISTS = 1050;

    public function up(): void
    {
        if (Schema::hasTable('octo_webhooks')) {
            return;
        }

        try {
            Schema::create('octo_webhooks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('affiliate_id')->index();
                $table->string('url', 500);
                $table->json('headers')->nullable();
                $table->json('events');
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? null) !== self::TABLE_ALREADY_EXISTS) {
                throw $e;
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('octo_webhooks');
    }
};
