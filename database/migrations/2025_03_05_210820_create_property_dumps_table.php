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
        Schema::create('property_dumps', function (Blueprint $table) {
            $table->id();
            $table->string('geom')->nullable();
            $table->string('enum_name')->nullable();
            $table->string('team')->nullable();
            $table->string('lga')->nullable();
            $table->string('zone')->nullable();
            $table->string('prop_owner')->nullable();
            $table->string('owner_emai')->nullable();
            $table->string('street_nam')->nullable();
            $table->string('street_n_1')->nullable();
            $table->string('prop_addre')->nullable();
            $table->string('land_statu')->nullable();
            $table->string('land_sta_1')->nullable();
            $table->string('land_sta_2')->nullable();
            $table->string('landuse')->nullable();
            $table->string('residentia')->nullable();
            $table->string('commercial')->nullable();
            $table->string('industrial')->nullable();
            $table->string('industri_1')->nullable();
            $table->string('education')->nullable();
            $table->string('agricultur')->nullable();
            $table->string('transport')->nullable();
            $table->string('utility')->nullable();
            $table->string('kgsg_publi')->nullable();
            $table->string('fgn_public')->nullable();
            $table->string('religious')->nullable();
            $table->string('others')->nullable();
            $table->string('occupier_s')->nullable();
            $table->string('no_of_floo')->nullable();
            $table->string('no_of_fl_1')->nullable();
            $table->string('property_n')->nullable();
            $table->string('water')->nullable();
            $table->string('signage')->nullable();
            $table->string('power')->nullable();
            $table->string('date_time')->nullable();
            $table->string('owner_phon')->nullable();
            $table->string('fid')->nullable();
            $table->string('bld_permit')->nullable();
            $table->string('floor_no')->nullable();
            $table->string('recreation')->nullable();
            $table->string('prop_name')->nullable();
            $table->string('kgtin')->nullable();
            $table->string('photo')->nullable();
            $table->string('longitude')->nullable();
            $table->string('latitude')->nullable();
            $table->string('ward')->nullable();
            $table->string('lga_abb')->nullable();
            $table->string('prop_no')->nullable();
            $table->string('property_age')->nullable();
            $table->string('land_status')->nullable();
            $table->string('property_area')->nullable();
            $table->string('photo_link')->nullable();
            $table->string('source')->nullable();
            $table->string('completeness_status')->nullable();
            $table->string('prop_id')->nullable();
            $table->string('bill_sync')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_dumps');
    }
};
