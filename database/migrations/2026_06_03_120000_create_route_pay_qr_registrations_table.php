<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_pay_qr_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bill_id')->nullable();
            $table->unsignedBigInteger('lga_id')->nullable();
            $table->string('assessment_no', 64)->nullable();
            $table->string('merchant_reference', 64)->index();
            $table->string('transaction_reference', 64)->nullable()->index();
            $table->decimal('amount_expected', 14, 2)->nullable();
            $table->string('webhook_status', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_pay_qr_registrations');
    }
};
