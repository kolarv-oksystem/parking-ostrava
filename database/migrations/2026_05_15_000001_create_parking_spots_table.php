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
        Schema::create('parking_spots', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('spot_number')->unique();
            $table->boolean('is_occupied')->default(false);
            $table->string('occupied_by_name', 100)->nullable();
            $table->string('occupied_by_device_id', 100)->nullable();
            $table->timestamp('occupied_at')->nullable();
            $table->boolean('is_reserved_service')->default(false);
            $table->timestamps();

            $table->index(['is_occupied', 'spot_number']);
            $table->index(['is_reserved_service', 'spot_number']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('parking_spots');
    }
};
