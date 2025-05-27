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
        Schema::table('reconciliations', function (Blueprint $table) {
            $table->string('assessment_no')->nullable()->after('entry_date');
            $table->string('payer_name')->nullable()->after('entry_date');
            $table->string('status')->default(0)->after('entry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reconciliations', function (Blueprint $table) {
            //
        });
    }
};
