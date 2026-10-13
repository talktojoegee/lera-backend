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
            'id' => $this->propertyId,
            'date' => date('d/m/Y', strtotime($this->created_at)),
            'buildingCode' => $this->buildingCode,
            'owner' => $this->owner_name ?? '',
            'pavCode' => $this->pav_code,
            'title' => $this->title ?? '',
            'lgaName' => $this->lga_name ?? '',
            'size' => $this->size ?? '',
            'area' => $this->area ?? '',
            'zoneName' => $this->sub_zone ?? '',
            'ward' => $this->ward ?? '',
            'occupancy' => $this->occupant ?? '',
            'class' => $this->property_use ?? '',
            'className' => $this->class_name ?? '',
            'propertyUse' => $this->property_use ?? '',
            'reason' => $this->reason ?? '',
            'status' => $this->status ?? '',
            'propertyName' => $this->property_name ?? '',
        ];

    }
}
