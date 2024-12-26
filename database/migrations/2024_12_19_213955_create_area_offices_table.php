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
        Schema::create('area_offices', function (Blueprint $table) {
            $table->id();
            $table->string('area_name');
            $table->string('area_office_id');
            $table->unsignedBigInteger('lga_id');
            $table->foreign('lga_id')
                ->references('id')
                ->on('lgas')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('area_offices');
    }
};
