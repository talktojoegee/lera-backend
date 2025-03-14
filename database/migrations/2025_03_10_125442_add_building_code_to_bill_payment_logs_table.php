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
        Schema::table('bill_payment_logs', function (Blueprint $table) {
            $table->string('building_code')->after('assessment_no')->nullable();
            $table->string('ward')->after('assessment_no')->nullable();
            $table->integer('lga_id')->after('assessment_no')->nullable();
            $table->date('entry_date')->after('assessment_no')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bill_payment_logs', function (Blueprint $table) {
            //
        });
    }
};
