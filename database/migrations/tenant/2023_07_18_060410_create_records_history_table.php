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
        Schema::create('records_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('record_id_1')->nullable()->index();
            $table->unsignedBigInteger('record_id_2')->nullable()->index()->comment('Generally to store id of the record in parent table, which can be used to get history of the children tables');
            $table->enum('operation_type', ['i', 'u', 'd', 'e'])->default('i')->comment('i = Insert, u = Update, d = Delete, e = Email');
            $table->string('table_name', 50)->default('')->index();
            $table->string('entity_info')->default('')->comment('Name/Title of the entity/record that has been inserted/updated/deleted');
            $table->text('old_values')->comment('JSON Object: { field_1: value_1, field_2: value_2, ... }');
            $table->text('new_values')->comment('JSON Object: { field_1: value_1, field_2: value_2, ... }');
            $table->dateTime('operation_at')->nullable()->index();
            $table->unsignedBigInteger('operation_by')->nullable();
            $table->enum('operation_by_user_type', ['Admin', 'Tour Operator'])->default('Tour Operator');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('records_history');
    }
};
