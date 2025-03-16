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
        Schema::create('settlement_report_setups', function (Blueprint $table) {
            $table->id();
            $table->double('bank')->default(0);
            $table->double('newwaves')->default(0);
            $table->double('kgirs')->default(0);
            $table->double('lga')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlement_report_setups');
    }
};
