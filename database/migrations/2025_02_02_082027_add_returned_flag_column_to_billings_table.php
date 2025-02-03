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
        Schema::table('billings', function (Blueprint $table) {
            $table->tinyInteger('returned')->default(0)->after('status')->comment('0=not,1=returned, 2=treated');
            $table->unsignedBigInteger('returned_by')->nullable()->after('status');
            $table->date('date_returned')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            //
        });
    }
};
