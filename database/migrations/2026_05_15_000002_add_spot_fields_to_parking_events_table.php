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
            $table->unsignedInteger('spot_number')->nullable()->after('device_id');
            $table->boolean('is_reserved_service')->default(false)->after('spot_number');
            $table->index(['spot_number', 'created_at']);
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
            $table->dropIndex(['spot_number', 'created_at']);
            $table->dropColumn(['spot_number', 'is_reserved_service']);
        });
    }
};
