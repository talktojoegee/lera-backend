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
        Schema::table('property_lists', function (Blueprint $table) {
            $table->string('property_type')->nullable()->after('property_name');
            $table->string('business_type')->nullable()->after('owner_name');
            $table->string('no_of_floors')->nullable()->after('owner_name');
            $table->string('no_of_units')->nullable()->after('owner_name');
            $table->string('revenue_category')->nullable()->after('owner_name');
            $table->string('photo_gate')->nullable()->after('owner_name');
            $table->string('photo_signage')->nullable()->after('owner_name');
            $table->string('photo_street')->nullable()->after('owner_name');
            $table->string('area_in_me')->nullable()->after('owner_name');
            $table->string('confidence')->nullable()->after('owner_name');
            $table->string('full_plus_')->nullable()->after('owner_name');
            $table->string('contact_name')->nullable()->after('owner_name');
            $table->string('contact_phone')->nullable()->after('owner_name');
            $table->string('contact_address')->nullable()->after('owner_name');
            $table->string('contact_email')->nullable()->after('owner_name');
            $table->string('confirmed_house_number')->nullable()->after('owner_name');
            $table->string('assigned_house_number')->nullable()->after('owner_name');
            $table->string('business_name_1')->nullable()->after('owner_name');
            $table->string('business_name_2')->nullable()->after('owner_name');
            $table->string('business_name_3')->nullable()->after('owner_name');
            $table->string('business_name_4')->nullable()->after('owner_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_lists', function (Blueprint $table) {
            //
        });
    }
};
