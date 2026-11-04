<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Add columns only if they don't exist
            if (!Schema::hasColumn('customers', 'country')) {
                $table->string('country', 100)->nullable()->comment('Country name from IP geolocation');
            }
            if (!Schema::hasColumn('customers', 'region')) {
                $table->string('region', 100)->nullable()->comment('Region/State name from IP geolocation');
            }
            if (!Schema::hasColumn('customers', 'city')) {
                $table->string('city', 100)->nullable()->comment('City name from IP geolocation');
            }
            if (!Schema::hasColumn('customers', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable()->comment('Latitude coordinate from IP geolocation');
            }
            if (!Schema::hasColumn('customers', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable()->comment('Longitude coordinate from IP geolocation');
            }
            if (!Schema::hasColumn('customers', 'timezone')) {
                $table->string('timezone', 50)->nullable()->comment('Timezone from IP geolocation');
            }
            if (!Schema::hasColumn('customers', 'isp')) {
                $table->string('isp', 255)->nullable()->comment('Internet Service Provider from IP geolocation');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $columnsToRemove = [];
            
            if (Schema::hasColumn('customers', 'country')) {
                $columnsToRemove[] = 'country';
            }
            if (Schema::hasColumn('customers', 'region')) {
                $columnsToRemove[] = 'region';
            }
            if (Schema::hasColumn('customers', 'city')) {
                $columnsToRemove[] = 'city';
            }
            if (Schema::hasColumn('customers', 'latitude')) {
                $columnsToRemove[] = 'latitude';
            }
            if (Schema::hasColumn('customers', 'longitude')) {
                $columnsToRemove[] = 'longitude';
            }
            if (Schema::hasColumn('customers', 'timezone')) {
                $columnsToRemove[] = 'timezone';
            }
            if (Schema::hasColumn('customers', 'isp')) {
                $columnsToRemove[] = 'isp';
            }
            
            if (!empty($columnsToRemove)) {
                $table->dropColumn($columnsToRemove);
            }
        });
    }
};
