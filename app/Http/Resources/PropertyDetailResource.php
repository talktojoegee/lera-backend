<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'buildingCode' => $this->building_code,

            'pavCode' => $this->pav_code,
            'title' => $this->title ?? '',
            'address' => $this->address ?? '',
            'propertyAddress' => $this->property_address ?? '',
            'lgaName' => $this->getLGA->lga_name ?? '',
            'size' => $this->size ?? '',
            'area' => $this->area ?? '',
            'ward' => $this->ward ?? '',
            'zoneName' => $this->sub_zone ?? '',
            'occupancy' => $this->occupant ?? '',
            'class' => $this->property_use ?? '',
            'className' => $this->getPropertyClassification->class_name ?? $this->class_name ?? '',
            'classId' => $this->getPropertyClassification->id ?? '',
            'propertyUse' => $this->property_type ?? $this->property_use ?? $this->sync_word ?? '',
            'propertyUseLabel' => $this->formatPropertyUseLabel($this->property_type ?? $this->property_use ?? $this->sync_word ?? ''),
            'kgTin' => $this->owner_kgtin,
            'ownerEmail' => $this->owner_email,
            'ownerGsm' => $this->owner_gsm,
            'owner' => $this->owner_name ?? '',
            'dep' => $this->dep_id ?? '',
            'cr' => $this->cr ?? '',

            'image' => $this->image,
            'borehole' => $this->borehole,
            'power' => $this->power,
            'refuse' => $this->refuse,
            'storey' => $this->storey,
            'water' => $this->water,
            'payStatus' => $this->pay_status,
            'buildingAge' => $this->building_age,
            'propertyName' => $this->property_name,
            'syncWord' => $this->sync_word,
            'lat' => $this->latitude ?? '',
            'long' => $this->longitude ?? '',

            'propertyType' => $this->property_type ?? $this->property_use ?? '',
            'businessName1' => $this->business_name_1 ?? '',
            'businessName2' => $this->business_name_2 ?? '',
            'confirmedHouseNo' => $this->confirmed_house_number ?? '',
            'streetName' => $this->street_name ?? '',
            'contactEmail' => $this->contact_email ?? '',
            'contactAddress' => $this->contact_address ?? '',
            'contactPhone' => $this->contact_phone ?? '',
            'contactName' => $this->contact_name ?? '',
        ];

        // return parent::toArray($request);
    }

    private function formatPropertyUseLabel(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return ucwords(str_replace('_', ' ', $value));
    }
}
