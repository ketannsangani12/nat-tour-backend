<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('octo_webhooks', function (Blueprint $table) {
            $table->text('headers')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('octo_webhooks', function (Blueprint $table) {
            $table->json('headers')->nullable()->change();
        });
    }
};
