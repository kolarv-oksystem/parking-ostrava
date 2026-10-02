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
        Schema::table('parking_spots', function (Blueprint $table) {
            $table->boolean('skip_auto_release')->default(false)->after('is_reserved_service');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('parking_spots', function (Blueprint $table) {
            $table->dropColumn('skip_auto_release');
        });
    }
};
