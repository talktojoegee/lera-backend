<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyListResource extends JsonResource
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
          "buildingCode"=>$this->building_code,
          "owner"=>$this->owner_name ?? '',
          "pavCode"=>$this->pav_code,
          "title"=>$this->title ?? '',
          "lgaName"=>$this->getLGA->lga_name ?? '',
          "size"=>$this->size ?? '',
          "area"=>$this->area ?? '',
          "zoneName"=>$this->sub_zone ?? '',
          "occupancy"=>$this->occupant ?? '',
          "class"=>$this->getPropertyClassification->class_name ?? '',
          "propertyUse"=>$this->property_use ?? ''
        ];
    }
}
