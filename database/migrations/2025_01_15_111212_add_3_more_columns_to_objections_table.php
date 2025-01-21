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
        Schema::table('objections', function (Blueprint $table) {
            $table->double('luc_amount')->default(0)->after('date_actioned');
            $table->double('rate')->default(0)->after('date_actioned');
            $table->double('assess_value')->default(0)->after('date_actioned');

            $table->unsignedBigInteger('authorized_by')->nullable()->after('assess_value');
            $table->dateTime('date_authorized')->nullable()->after('assess_value');

            $table->unsignedBigInteger('approved_by')->nullable()->after('date_authorized');
            $table->dateTime('date_approved')->nullable()->after('date_authorized');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('objections', function (Blueprint $table) {
            //
        });
    }
};
