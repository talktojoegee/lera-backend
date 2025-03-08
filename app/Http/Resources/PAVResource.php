<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PAVResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        //return parent::toArray($request);
        return [
            "id"=>$this->id,
            //"assessedAmount"=>$this->assessed_amount,
            //"valueRate"=>$this->value_rate,
            "ba"=>$this->ba,
            "rr"=>$this->rr,
            "br"=>$this->br,
            "lr"=>$this->lr,
            "propertyUse"=>$this->property_use,
            "occupancy"=>$this->property_use,
            "pavCode"=>$this->pav_code,
            "zones"=>$this->zones,
            "className"=>$this->getClass->class_name ?? '',
            "syncWord"=>$this->sync_word ?? '',
        ];
    }
}
