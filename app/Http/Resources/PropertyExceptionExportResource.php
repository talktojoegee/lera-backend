<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyExceptionExportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return [
            "buildingCode"=>$this->building_code,
            "owner"=>$this->owner_name ?? '',
            "pavCode"=>$this->pav_code,
            "zoneName"=>$this->sub_zone ?? '',
            "lgaName"=>$this->getLGA->lga_name ?? '',
            "class"=>$this->class_name ?? '',
            "propertyUse"=>$this->sync_word ?? '',
            "occupancy"=>$this->occupant ?? '',
            "propertyName"=>$this->property_name ?? '',
            "status"=>$this->status == 0 ? 'Not sync' : 'Sync',
            "reason"=>$this->reason ?? '',
        ];
    }
}
