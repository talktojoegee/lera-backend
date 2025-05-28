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
        Schema::table('reconciliations', function (Blueprint $table) {
            $table->unsignedBigInteger('reconciled_by')->nullable();
            $table->date('date_reconciled')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reconciliations', function (Blueprint $table) {
            //
        });
    }
};
