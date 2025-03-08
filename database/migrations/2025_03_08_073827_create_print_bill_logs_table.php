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
        Schema::create('print_bill_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bill_id');
            $table->tinyInteger('status')->default(0)->comment('0=not printed,1=printed');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('batch_code')->nullable();
            $table->string('printed_by')->nullable()->comment('ward,lga,zone,etc');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_bill_logs');
    }
};
