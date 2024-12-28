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
        Schema::create('synchronization_logs', function (Blueprint $table) {
            $table->id();
            $table->string('g_gis')->nullable();
            $table->string('k_labs')->nullable();
            $table->string('last_sync')->nullable();
            $table->integer('lga_id')->nullable();

            /*$table->foreign('lga_id')->references('id')
                ->on('lgas')
                ->onDelete('cascade');*/

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('synchronization_logs');
    }
};
