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
        Schema::create('property_exceptions', function (Blueprint $table) {
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

            $table->string('actual_age')->nullable();
            $table->string('longitude')->nullable();
            $table->string('latitude')->nullable();
            $table->string('property_no')->nullable();
            $table->string('property_name')->nullable();
            $table->string('occupier')->nullable();
            $table->integer('dep_id')->nullable();
            $table->string('property_address')->nullable();
            $table->double('cr')->nullable();
            $table->double('br')->default(0);
            $table->double('lr')->default(0);
            $table->double('rr')->default(0);
            $table->double('ba')->default(0);
            $table->tinyInteger('special')->default(0)->comment('0=default,1=special interest');
            $table->string('class_name')->nullable();
            $table->string('sub_zone')->nullable();
            $table->string('occupant')->nullable();
            $table->string('building_age')->nullable();
            $table->string('pay_status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_exceptions');
    }
};
