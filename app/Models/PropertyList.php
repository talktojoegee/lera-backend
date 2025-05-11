<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
        'class_name',
        'sub_zone',
        'ward',
        'occupant',
        'building_age',
        'pay_status',
        'cr',
        'ba',
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

    public static function searchAllProperties($keyword, $propertyUse){
        return DB::table('property_lists')
            ->leftJoin('property_classifications', 'property_lists.class_id', '=', 'property_classifications.id')
            ->leftJoin('lgas', 'lgas.id', '=', 'property_lists.lga_id')
            ->select(
                'property_lists.id as propertyId',
                'property_lists.building_code as buildingCode',
                'property_lists.address',
                'property_lists.property_address',
                'property_lists.area',
                'property_lists.borehole',
                'property_lists.image',
                'property_lists.owner_email',
                'property_lists.owner_gsm',
                'property_lists.owner_kgtin',
                'property_lists.owner_name',
                'property_lists.pav_code',
                'property_lists.zone_name',
                'property_lists.building_age',
                'property_lists.occupant',
                'property_lists.sub_zone',
                'property_lists.ward',
                'property_lists.class_name',
                'property_lists.cr',
                'property_lists.property_name',
                'property_lists.occupier',
                'property_lists.sync_word',
                'property_lists.property_use',
                'property_lists.created_at',
                'lgas.lga_name',
                'property_classifications.class_name',
            )
            ->whereIn('property_lists.property_use', $propertyUse)
            ->where(function ($query) use ($keyword) {
                $query->where('property_lists.building_code', 'LIKE', "%{$keyword}%")
                    ->orWhere('property_lists.pav_code', 'LIKE', "%{$keyword}%")
                    ->orWhere('property_lists.property_name', 'LIKE', "%{$keyword}%")
                    ->orWhere('property_lists.property_use', 'LIKE', "%{$keyword}%")
                    ->orWhere('property_classifications.class_name', 'LIKE', "%{$keyword}%")
                    ->orWhere('property_lists.ward', 'LIKE', "%{$keyword}%")
                    ->orWhere('lgas.lga_name', 'LIKE', "%{$keyword}%");
            })
            ->distinct()
            ->get();
    }
}
