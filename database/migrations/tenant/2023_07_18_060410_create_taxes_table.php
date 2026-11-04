<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('taxes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('state_id')->nullable()->index('taxes_state_id');
            $table->string('name', 80)->nullable()->index();
            $table->decimal('value', 10, 2)->default(0);
            $table->enum('value_type', ['Percent', 'Fixed Money'])->default('Percent');
            $table->enum('status', ['Inactive', 'Active'])->default('Active');
            $table->dateTime('created_at')->useCurrent()->nullable()->index();
            $table->unsignedInteger('created_by')->nullable()->index('taxes_created_by');
            $table->dateTime('updated_at')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->unsignedInteger('deleted_by')->nullable()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('taxes');
    }
};
