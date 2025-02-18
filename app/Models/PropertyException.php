<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyException extends Model
{
    protected $fillable = [
        'address',
        'area',
        'borehole',
        'building_code',
        'image',
        'owner_email',
        'owner_gsm',
        'owner_kgtin',
        'owner_name',
        'pav_code',
        'power',
        'refuse',
        'size',
        'storey',
        'title',
        'water',
        'zone_name',
        'lga_id',
        'class_id',
        'class_name',
        'sub_zone',
        'occupant',
        'building_age',
        'pay_status',
        'cr',
        'median_age',
        'actual_age',
        'longitude',
        'latitude',
        'property_name',
        'occupier',
        'dep_id',
        'property_address',
        'sync_word',
        'property_use',
    ];

    public function getLGA(){
        return $this->belongsTo(Lga::class, "lga_id");
    }
    public function getPropertyClassification(){
        return $this->belongsTo(PropertyClassification::class, "class_id");
    }
}
