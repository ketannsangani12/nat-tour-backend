<?php

use App\Models\TourOperator;
use App\Models\TourPackage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTourPackageFamiliesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tour_package_families', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(TourOperator::class);
            $table->foreignIdFor(TourPackage::class, 'parent_package_id');
            $table->foreignIdFor(TourPackage::class);
            $table->string('time_from')->index();
            $table->tinyInteger('is_parent')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tour_package_families');
    }
}
