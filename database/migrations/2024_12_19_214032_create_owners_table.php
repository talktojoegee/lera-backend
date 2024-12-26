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
        Schema::create('owners', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('kgtin');
            $table->string('name');
            $table->string('res_address');
            $table->string('telephone');
            $table->unsignedBigInteger('lga_id');
            $table->unsignedBigInteger('added_by');

            $table->foreign('lga_id')
                ->references('id')
                ->on('lgas')
                ->onDelete('cascade');
            $table->foreign('added_by')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('owners');
    }
};
