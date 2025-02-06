<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyAssessmentValue extends Model
{
    protected $fillable = [
      "assessed_amount",
      "value_rate",
      "property_use",
      "pav_code",
      "zones",
      "class_id",
      "lr",
      "ba",
      "rr",
      "br",
    ];

    public function getClass(){
        return $this->belongsTo(PropertyClassification::class, 'class_id');
    }


    public function getZonesByIds($ids){
        return Zone::whereIn('id', $ids)->pluck('sub_zone');
    }
}
