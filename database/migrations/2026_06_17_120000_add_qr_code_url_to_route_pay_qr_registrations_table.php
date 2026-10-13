<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_pay_qr_registrations', function (Blueprint $table) {
            $table->text('qr_code_url')->nullable()->after('transaction_reference');
        });
    }

    public function down(): void
    {
        Schema::table('route_pay_qr_registrations', function (Blueprint $table) {
            $table->dropColumn('qr_code_url');
        });
    }
};
