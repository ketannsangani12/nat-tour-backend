<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('key', 50)->nullable();
                $table->string('value', 100)->nullable();
                $table->timestamps();
            });
        }

        $exists = DB::table('settings')
            ->where('key', 'MAINTENANCE_MODE')
            ->exists();

        if (! $exists) {
            DB::table('settings')->insert([
                'key'        => 'MAINTENANCE_MODE',
                'value'      => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('settings') && ! Schema::hasColumn('settings', 'order_number')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->bigInteger('order_number')->after('value')->default(0);
            });
        }

        if (Schema::hasTable('settings') && ! Schema::hasColumn('settings', 'comment')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->longText('comment')->after('order_number')->nullable();
            });
        }

        if (Schema::hasTable('settings') && ! Schema::hasColumn('settings', 'input_type')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->string('input_type')->after('comment')->nullable();
            });
        }

        if (Schema::hasTable('settings') && ! Schema::hasColumn('settings', 'updated_by')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('updated_at');
            });
        }

        DB::table('settings')
            ->where('key', 'MAINTENANCE_MODE')
            ->whereNull('comment')
            ->update(['comment' => '0 - Disabled, 1 - Enabled : Put all tenant sites into maintenance mode']);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'MAINTENANCE_MODE')
            ->delete();

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['order_number', 'comment', 'input_type', 'updated_by']);
        });
    }
};
