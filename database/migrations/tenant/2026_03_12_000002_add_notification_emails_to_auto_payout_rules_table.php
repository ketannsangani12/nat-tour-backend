<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {



        Schema::table('auto_payout_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('auto_payout_rules', 'notification_emails')) {       
            $table->json('notification_emails')->nullable()->after('buffer_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auto_payout_rules', function (Blueprint $table) {
            $table->dropColumn('notification_emails');
        });
    }
};
