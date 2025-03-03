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
        Schema::create('kogi_rems_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('assessmentno')->nullable();
            $table->string('buildingcode')->nullable();
            $table->string('kgtin')->nullable();
            $table->string('name')->nullable();
            $table->string('amount')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('transdate')->nullable();
            $table->string('transRef')->nullable();
            $table->string('paymode')->nullable();
            $table->tinyInteger('status')->default(0)->comment('0=pending,1=sent,2=failed');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kogi_rems_notifications');
    }
};
