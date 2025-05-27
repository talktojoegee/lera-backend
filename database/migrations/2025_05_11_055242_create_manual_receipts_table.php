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
        Schema::create('manual_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('issued_by');
            $table->string('assessment_no');
            $table->double('amount')->default(0);
            $table->string('proof_of_payment');
            $table->tinyInteger('status')->default(0)->comment('0=pending,1=approve,2=discarded');
            $table->unsignedBigInteger('actioned_by')->nullable();
            $table->date('date_actioned')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manual_receipts');
    }
};
