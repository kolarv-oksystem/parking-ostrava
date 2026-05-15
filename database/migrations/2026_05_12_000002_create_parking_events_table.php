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
        Schema::create('parking_events', function (Blueprint $table) {
            $table->id();
            $table->string('action', 32);
            $table->integer('delta')->default(0);
            $table->unsignedInteger('old_value');
            $table->unsignedInteger('new_value');
            $table->timestamp('created_at')->useCurrent();
            $table->string('actor', 100)->nullable();
            $table->string('device_id', 100)->nullable();
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('parking_events');
    }
};
