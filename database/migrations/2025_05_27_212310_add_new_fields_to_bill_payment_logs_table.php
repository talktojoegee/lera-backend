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
        Schema::table('bill_payment_logs', function (Blueprint $table) {
            $table->string('reconciled')->default(0)->comment('0=No,1=Yes');
            $table->date('value_date')->nullable();
            $table->text('reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bill_payment_logs', function (Blueprint $table) {
            //
        });
    }
};
