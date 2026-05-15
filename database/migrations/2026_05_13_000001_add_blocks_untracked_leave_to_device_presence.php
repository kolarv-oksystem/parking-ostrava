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
        Schema::table('device_presence', function (Blueprint $table) {
            $table->boolean('blocks_untracked_leave_until_arrive')->default(false)->after('is_parked');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('device_presence', function (Blueprint $table) {
            $table->dropColumn('blocks_untracked_leave_until_arrive');
        });
    }
};
