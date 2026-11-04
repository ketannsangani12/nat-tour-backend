<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('octo_whitelisted_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip')->unique()->comment('Exact IPv4 address or CIDR range permitted to call the OCTO API.');
            $table->string('label')->comment('Who/what this entry is for, e.g. tester name or proxy description.');
            $table->unsignedBigInteger('created_by')->nullable()->comment('admins.id of the admin who added this entry.');
            // dateTime, not timestamp — MySQL TIMESTAMP columns convert to/from UTC on
            // the session timezone, which doesn't match app.timezone (America/Phoenix)
            // and silently shifts the stored instant. dateTime stores the literal value.
            $table->dateTime('expires_at')->nullable()->comment('Optional auto-expiry. Null means it never expires.');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('octo_whitelisted_ips');
    }
};
