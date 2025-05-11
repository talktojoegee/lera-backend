<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
        'reason',
        'status',
    ];

    public function getLGA(){
        return $this->belongsTo(Lga::class, "lga_id");
    }
    public function getPropertyClassification(){
        return $this->belongsTo(PropertyClassification::class, "class_id");
    }


    public static function searchAllPropertyException($keyword/*, $propertyUse*/){
        return DB::table('property_exceptions')
            ->leftJoin('property_classifications', 'property_exceptions.class_id', '=', 'property_classifications.id')
            ->leftJoin('lgas', 'lgas.id', '=', 'property_exceptions.lga_id')
            ->select(
                'property_exceptions.id as propertyId',
                'property_exceptions.building_code as buildingCode',
                'property_exceptions.address',
                'property_exceptions.property_address',
                'property_exceptions.area',
                'property_exceptions.borehole',
                'property_exceptions.image',
                'property_exceptions.owner_email',
                'property_exceptions.owner_gsm',
                'property_exceptions.owner_kgtin',
                'property_exceptions.owner_name',
                'property_exceptions.pav_code',
                'property_exceptions.zone_name',
                'property_exceptions.building_age',
                'property_exceptions.occupant',
                'property_exceptions.sub_zone',
                //'property_exceptions.ward',
                'property_exceptions.class_name',
                'property_exceptions.cr',
                'property_exceptions.property_name',
                'property_exceptions.occupier',
                'property_exceptions.sync_word',
                'property_exceptions.property_use',
                'property_exceptions.reason',
                'property_exceptions.status',
                'property_exceptions.created_at',

                'lgas.lga_name',
                'property_classifications.class_name',
            )
            //->whereIn('property_exceptions.property_use', $propertyUse)
            ->where(function ($query) use ($keyword) {
                $query->where('property_exceptions.building_code', 'LIKE', "%{$keyword}%")
                    ->orWhere('property_exceptions.pav_code', 'LIKE', "%{$keyword}%")
                    ->orWhere('property_exceptions.property_name', 'LIKE', "%{$keyword}%")
                    ->orWhere('property_exceptions.property_use', 'LIKE', "%{$keyword}%")
                    ->orWhere('property_exceptions.sub_zone', 'LIKE', "%{$keyword}%")
                    ->orWhere('property_classifications.class_name', 'LIKE', "%{$keyword}%")
                    //->orWhere('property_exceptions.ward', 'LIKE', "%{$keyword}%")
                    ->orWhere('lgas.lga_name', 'LIKE', "%{$keyword}%");
            })
            ->distinct()
            ->get();
    }
}
