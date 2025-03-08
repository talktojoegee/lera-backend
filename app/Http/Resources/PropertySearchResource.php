<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertySearchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id"=>$this->propertyId,
            "buildingCode"=>$this->buildingCode,
            "owner"=>$this->owner_name ?? '',
            "pavCode"=>$this->pav_code,
            "title"=>$this->title ?? '',
            "lgaName"=>$this->lga_name ?? '',
            "size"=>$this->size ?? '',
            "area"=>$this->area ?? '',
            "zoneName"=>$this->sub_zone ?? '',
            "ward"=>$this->ward ?? '',
            "occupancy"=>$this->occupant ?? '',
            "class"=>$this->class_name ?? '',
            "propertyUse"=>$this->sync_word ?? '',
            "reason"=>$this->reason ?? '',
            "status"=>$this->status ?? '',
            "propertyName"=>$this->property_name ?? '',
        ];
    }
}
