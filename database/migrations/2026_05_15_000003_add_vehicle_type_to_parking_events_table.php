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
        Schema::table('parking_events', function (Blueprint $table) {
            $table->string('vehicle_type', 16)->nullable()->after('is_reserved_service');
            $table->index(['vehicle_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('parking_events', function (Blueprint $table) {
            $table->dropIndex(['vehicle_type', 'created_at']);
            $table->dropColumn('vehicle_type');
        });
    }
};
