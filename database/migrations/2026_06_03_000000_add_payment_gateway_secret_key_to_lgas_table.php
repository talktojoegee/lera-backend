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
        Schema::table('lgas', function (Blueprint $table) {
            $table->text('payment_gateway_secret_key')->nullable()->after('lga_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lgas', function (Blueprint $table) {
            $table->dropColumn('payment_gateway_secret_key');
        });
    }
};
