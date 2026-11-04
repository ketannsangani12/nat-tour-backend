<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sent_emails', function (Blueprint $table) {
            if (!collect(DB::select("SHOW INDEX FROM sent_emails WHERE Key_name = 'idx_sent_emails_created_at'"))->count()) {
                $table->index('created_at', 'idx_sent_emails_created_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sent_emails', function (Blueprint $table) {
            $table->dropIndex('idx_sent_emails_created_at');
        });
    }
};
