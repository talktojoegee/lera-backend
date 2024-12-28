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
            "assessedAmount"=>$this->assessed_amount,
            "valueRate"=>$this->value_rate,
            "occupancy"=>$this->occupancy,
            "pavCode"=>$this->pav_code,
            "zones"=>$this->zones,
            "className"=>$this->getClass->class_name,
        ];
    }
}
