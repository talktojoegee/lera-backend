<?php

use App\Models\Lga;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (Lga::DEPRECATED_RETURN_URL_HOSTS as $host) {
            DB::table('lgas')
                ->where('payment_gateway_qr_return_url', 'like', '%'.$host.'%')
                ->update(['payment_gateway_qr_return_url' => null]);
        }
    }

    public function down(): void
    {
        // Cannot restore retired hostnames safely.
    }
};
