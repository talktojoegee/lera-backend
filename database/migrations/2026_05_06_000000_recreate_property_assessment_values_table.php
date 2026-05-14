<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Drops the existing table (if any) and recreates it with the requested columns.
     * WARNING: this will permanently remove any existing columns/data in the table.
     * Make sure you have backups if you need to preserve existing data.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('property_assessment_values')) {
            Schema::drop('property_assessment_values');
        }

        Schema::create('property_assessment_values', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('pav_code')->nullable()->index();
            $table->string('zones')->nullable();
            $table->string('property_type')->nullable();
            // annual_due stored as decimal, adjust precision/scale if needed
            $table->decimal('annual_due', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     * Drops the modified table and recreates a minimal placeholder table.
     * (Original columns cannot be restored by this migration because they are unknown.)
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('property_assessment_values');

        // Recreate a minimal fallback table so rollback leaves a usable table.
        Schema::create('property_assessment_values', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('pav_code')->nullable();
            $table->timestamps();
        });
    }
};

