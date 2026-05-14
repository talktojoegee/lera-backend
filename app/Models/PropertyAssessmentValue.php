<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyAssessmentValue extends Model
{
    protected $fillable = [
      'pav_code',
      'zones',
      'property_type',
      'annual_due',
    ];

    public function getClass(){
        return $this->belongsTo(PropertyClassification::class, 'class_id');
    }


    public function getZonesByIds($ids){
        return Zone::whereIn('id', $ids)->pluck('sub_zone');
    }

    public static function getDistinctPropertyUses()
    {
        return PropertyAssessmentValue::query()
            ->select('property_type')
            ->whereNotNull('property_type')
            ->groupBy('property_type')
            ->orderBy('property_type')
            ->get();
    }
}
