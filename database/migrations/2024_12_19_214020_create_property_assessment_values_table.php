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
        Schema::create('property_assessment_values', function (Blueprint $table) {
            $table->id();
            $table->double('assessed_amount')->default(0);
            $table->double('value_rate')->default(0);
            $table->string('occupancy')->nullable();
            $table->string('pav_code')->nullable();
            $table->text('zones')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();

            $table->foreign('class_id')
                ->references('id')
                ->on('property_classifications')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_assessment_values');
    }
};
