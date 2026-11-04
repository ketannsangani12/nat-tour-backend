<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('addon_categories')) {
            // Concurrent tenant migration run already created (and seeded) this table.
            return;
        }

        try {
            Schema::create('addon_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('slug')->nullable();
                $table->enum('status', ['Active', 'Inactive'])->default('Active');
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        } catch (QueryException $e) {
            // 42S01 = table already exists. Another process (concurrent tenant
            // migration run) won the race and already created + seeded it.
            if ($e->getCode() === '42S01') {
                return;
            }

            throw $e;
        }

        $now = now();

        DB::table('addon_categories')->insert(collect([
            'Default' => 10,
            'Transportation' => 20,
            'Meals & Beverage' => 30,
            'Equipment Rental' => 40,
            'Merchandise' => 50,
            'Photography & Media' => 60,
            'Guide Services' => 70,
            'Insurance' => 80,
            'Additional Package' => 90,
        ])->map(fn ($sortOrder, $name) => [
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => 'Active',
            'sort_order' => $sortOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->toArray());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addon_categories');
    }
};
