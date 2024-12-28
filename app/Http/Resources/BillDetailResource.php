<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillDetailResource extends JsonResource
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
            'ownerName'=>$this->owner_name ?? 'N/A',
            'buildingCode'=>$this->building_code ?? '',
            'contactAddress'=>$this->getPropertyList->address ?? '',
            'propertyClassification'=>$this->getPropertyList->getClass->class_name ?? '',
            'kgTin'=>$this->getPropertyList->owner_kgtin,
            'entryDate'=>date('d M, Y', strtotime($this->entry_date)),
            'assessmentNo'=>$this->assessment_no ?? '',
            'propertyAddress'=>$this->getPropertyList->address ?? '',
            'phoneNo'=>$this->getPropertyList->owner_gsm ?? '',
            'assessValue'=>number_format($this->assessed_value ?? 0,2),
            'chargeRate'=>$this->bill_rate ?? 0,
            'year'=>$this->year,
            'billAmount'=>number_format($this->bill_amount ?? 0,2),
            'url'=>$this->url
        ];
    }
}
