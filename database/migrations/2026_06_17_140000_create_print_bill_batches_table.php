<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_bill_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code', 64)->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('printed_by')->nullable();
            $table->unsignedInteger('bill_count')->default(0);
            $table->string('pdf_status', 32)->default('pending');
            $table->string('pdf_path')->nullable();
            $table->text('pdf_error')->nullable();
            $table->timestamp('pdf_generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_bill_batches');
    }
};
