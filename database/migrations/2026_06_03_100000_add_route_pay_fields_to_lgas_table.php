<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lgas', function (Blueprint $table) {
            $table->string('payment_gateway_client_id')->nullable()->after('payment_gateway_secret_key');
            $table->string('payment_gateway_auth_url')->nullable()->after('payment_gateway_client_id');
            $table->string('payment_gateway_base_url')->nullable()->after('payment_gateway_auth_url');
            $table->string('payment_gateway_qr_generate_url')->nullable()->after('payment_gateway_base_url');
            $table->string('payment_gateway_qr_return_url')->nullable()->after('payment_gateway_qr_generate_url');
            $table->unsignedInteger('payment_gateway_qr_duration_seconds')->nullable()->after('payment_gateway_qr_return_url');
            $table->boolean('payment_gateway_qr_verify_me')->default(true)->after('payment_gateway_qr_duration_seconds');
            $table->text('payment_gateway_qr_alternate_base_urls')->nullable()->after('payment_gateway_qr_verify_me');
        });
    }

    public function down(): void
    {
        Schema::table('lgas', function (Blueprint $table) {
            $table->dropColumn([
                'payment_gateway_client_id',
                'payment_gateway_auth_url',
                'payment_gateway_base_url',
                'payment_gateway_qr_generate_url',
                'payment_gateway_qr_return_url',
                'payment_gateway_qr_duration_seconds',
                'payment_gateway_qr_verify_me',
                'payment_gateway_qr_alternate_base_urls',
            ]);
        });
    }
};
