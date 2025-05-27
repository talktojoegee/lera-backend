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
        Schema::table('manual_receipts', function (Blueprint $table) {
            $table->string('receipt_no');
            $table->date('entry_date');
            $table->string('bank_name')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('customer_name');
            $table->string('email')->nullable();
            $table->string('kgtin')->nullable();
            $table->string('reference')->nullable();
            $table->string('token')->default('MANUAL_RECEIPT');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manual_receipts', function (Blueprint $table) {
            //
        });
    }
};
