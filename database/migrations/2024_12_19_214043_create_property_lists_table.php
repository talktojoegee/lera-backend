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
        Schema::create('property_lists', function (Blueprint $table) {
            $table->id();
            $table->string('address')->nullable();
            $table->string('area')->nullable();
            $table->integer('borehole')->default(0);
            $table->string('building_code')->nullable();
            $table->string('image')->nullable();
            $table->string('owner_email')->nullable();
            $table->string('owner_gsm')->nullable();
            $table->string('owner_kgtin')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('pav_code')->nullable();
            $table->integer('power')->default(0);
            $table->string('refuse')->nullable();
            $table->string('size')->nullable();
            $table->integer('storey')->default(0);
            $table->string('title')->nullable();
            $table->integer('water')->default(0);
            $table->string('zone_name')->nullable();
            $table->unsignedBigInteger('lga_id');
            $table->unsignedBigInteger('class_id');


            $table->foreign('lga_id')
                ->references('id')
                ->on('lgas')
                ->onDelete('cascade');

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
        Schema::dropIfExists('property_lists');
    }
};
