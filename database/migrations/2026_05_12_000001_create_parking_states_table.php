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
        Schema::create('parking_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('capacity_total');
            $table->unsignedInteger('free_spots');
            $table->string('updated_by')->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->string('manual_update_password_hash');
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
        Schema::dropIfExists('parking_states');
    }
};
