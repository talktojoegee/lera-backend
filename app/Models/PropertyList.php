<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyList extends Model
{
    //
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
    ];


    public function getLGA(){
        return $this->belongsTo(Lga::class, "lga_id");
    }
    public function getPropertyClassification(){
        return $this->belongsTo(PropertyClassification::class, "class_id");
    }
}
