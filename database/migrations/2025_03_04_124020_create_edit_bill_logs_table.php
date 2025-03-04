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
        Schema::create('edit_bill_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bill_id');
            $table->unsignedBigInteger('edited_by');
            $table->string('building_code');
            $table->double('prev_la')->default(0);
            $table->double('prev_ba')->default(0);
            $table->double('prev_rr')->default(0);
            $table->double('prev_dr')->default(0);
            $table->double('prev_br')->default(0);
            $table->double('prev_lr')->default(0);
            $table->double('prev_luc')->default(0);
            $table->double('prev_assess_value')->default(0);
            $table->double('cur_la')->default(0);
            $table->double('cur_ba')->default(0);
            $table->double('cur_rr')->default(0);
            $table->double('cur_dr')->default(0);
            $table->double('cur_br')->default(0);
            $table->double('cur_lr')->default(0);
            $table->double('cur_luc')->default(0);
            $table->double('cur_assess_value')->default(0);
            $table->timestamps();
        });
    }
/*
 * la
ba
rr
dr
br
lr
 */
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edit_bill_logs');
    }
};
