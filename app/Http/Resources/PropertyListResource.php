<?php

namespace App\Http\Resources;

use App\Models\Billing;
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
        // return parent::toArray($request);
        $bill = Billing::where('property_id', $this->id)->get();

        return [
            'id' => $this->id,
            'date' => date('d/m/Y', strtotime($this->created_at)),
            'buildingCode' => $this->building_code,
            'owner' => $this->owner_name ?? '',
            'image' => $this->image ?? '',
            'pavCode' => $this->pav_code,
            'title' => $this->title ?? '',
            'lgaName' => $this->getLGA->lga_name ?? '',
            'size' => $this->size ?? '',
            'area' => $this->area ?? '',
            'zoneName' => $this->sub_zone ?? '',
            'ward' => $this->ward ?? '',
            'occupancy' => $this->occupant ?? '',
            'class' => $this->property_use ?? '',
            'className' => $this->getPropertyClassification->class_name ?? $this->class_name ?? '',
            'address' => $this->address ?? '',
            'propertyUse' => $this->property_use ?? '',
            'reason' => $this->reason ?? '',
            'status' => $this->status ?? '',
            'propertyName' => $this->property_name ?? '',
            'billExistCounter' => ! empty($bill) ? $bill->count() : 0,
            'lat' => $this->latitude ?? '',
            'long' => $this->longitude ?? '',

            'propertyType' => $this->property_use ?? '',
            'businessName1' => $this->business_name_1 ?? '',
            'businessName2' => $this->business_name_2 ?? '',
            'confirmedHouseNo' => $this->confirmed_house_number ?? '',
            'streetName' => $this->street_name ?? '',
            'contactEmail' => $this->contact_email ?? '',
            'contactAddress' => $this->contact_address ?? '',
            'contactPhone' => $this->contact_phone ?? '',
            'contactName' => $this->contact_name ?? '',
        ];
    } // extension_dir => /usr/lib/php/20230831 => /usr/lib/php/20230831
}
