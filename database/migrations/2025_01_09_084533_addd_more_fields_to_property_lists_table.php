<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('property_lists', function (Blueprint $table) {
            $table->string('class_name')->after('zone_name')->nullable();
            $table->string('sub_zone')->after('zone_name')->nullable();
            $table->string('occupant')->after('zone_name')->nullable();
            $table->string('building_age')->after('zone_name')->nullable();
            $table->string('pay_status')->after('zone_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_lists', function (Blueprint $table) {
            //
        });
    }
};
