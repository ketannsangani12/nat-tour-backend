<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('octo_keys', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->comment('Tenant ID, e.g. dixies → tenant_dixies');
            $table->string('reseller')->comment('OTA platform that owns this key, e.g. headout, viator');
            $table->char('api_key_hash', 64)->comment('SHA-256 hash of the plaintext bearer token. The plaintext is never stored.');
            $table->string('key_prefix', 20)->comment('First 20 characters of the plaintext key for identification only. Never the full key.');
            $table->boolean('is_active')->default(true)->comment('Set to false to revoke access without deleting the record.');
            $table->timestamps();

            $table->unique(['tenant_id', 'reseller']);
            $table->index('api_key_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('octo_keys');
    }
};
