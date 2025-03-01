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
            $table->string('receipt_no')->nullable();
            $table->string('payment_code')->nullable();
            $table->string('assessment_no')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('pay_mode')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('email')->nullable();
            $table->string('kgtin')->nullable();
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
