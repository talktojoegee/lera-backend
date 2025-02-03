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
        Schema::table('property_assessment_values', function (Blueprint $table) {
            $table->double('br')->default(0)->after('value_rate');
            $table->double('lr')->default(0)->after('value_rate');
            $table->double('rr')->default(0)->after('value_rate');
            $table->double('ba')->default(0)->after('value_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_assessment_values', function (Blueprint $table) {
            //
        });
    }
};
