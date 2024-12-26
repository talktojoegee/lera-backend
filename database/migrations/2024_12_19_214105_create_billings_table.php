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
        Schema::create('billings', function (Blueprint $table) {
            $table->id();
            $table->double('assessed_value')->default(0);
            $table->double('bill_amount')->default(0);
            $table->double('bill_rate')->default(0);
            $table->double('paid_amount')->default(0);
            $table->string('assessment_no')->nullable();
            $table->string('building_code')->nullable();
            $table->date('entry_date')->nullable();
            $table->tinyInteger('objection')->default(0);
            $table->tinyInteger('paid')->default(0);
            $table->string('pav_code')->default(0);
            $table->integer('year')->default(0);
            $table->unsignedBigInteger('lga_id');
            $table->unsignedBigInteger('billed_by');
            $table->unsignedBigInteger('property_id');

            $table->foreign('lga_id')
                ->references('id')
                ->on('lgas')
                ->onDelete('cascade');

            $table->foreign('billed_by')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('property_id')
                ->references('id')
                ->on('property_lists')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billings');
    }
};
