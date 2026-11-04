<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// `body` on both email-template tables is a `TEXT` column, capped at 65,535 bytes.
// This app's DB connections run with `'strict' => false` (config/database.php), so
// MySQL doesn't raise "Data too long for column" when a value exceeds that cap - it
// silently truncates to fit. Table-based HTML email bodies with heavy inline styling
// routinely exceed 65KB once run through a rich-text editor, so saves were being cut
// off with no error shown. `mediumtext` raises the cap to ~16MB.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_package_email_templates', function (Blueprint $table) {
            $table->mediumText('body')->change();
        });

        Schema::table('email_templates', function (Blueprint $table) {
            $table->mediumText('body')->change();
        });
    }

    public function down(): void
    {
        Schema::table('tour_package_email_templates', function (Blueprint $table) {
            $table->text('body')->change();
        });

        Schema::table('email_templates', function (Blueprint $table) {
            $table->text('body')->change();
        });
    }
};
