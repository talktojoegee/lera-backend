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
        Schema::create('objections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("bill_id");
            $table->unsignedBigInteger("submitted_by");
            $table->longText("reason")->nullable();
            $table->string("relief_ids")->nullable();
            $table->tinyInteger('status')->default(0)->comment('0=pending,1=verified,2=declined,3=authorized,4=approved');
            $table->unsignedBigInteger('actioned_by')->nullable();
            $table->dateTime('date_actioned')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('objections');
    }
};
