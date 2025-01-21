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
        Schema::table('billings', function (Blueprint $table) {

            $table->unsignedBigInteger('actioned_by')->nullable()->after('zone_name');
            $table->dateTime('date_actioned')->nullable()->after('zone_name');



            $table->unsignedBigInteger('authorized_by')->nullable()->after('zone_name');
            $table->dateTime('date_authorized')->nullable()->after('zone_name');

            $table->unsignedBigInteger('approved_by')->nullable()->after('zone_name');
            $table->dateTime('date_approved')->nullable()->after('zone_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            //
        });
    }
};
