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
            'ownerName'=>$this->getPropertyList->owner_name ?? '',
            'buildingCode'=>$this->building_code ?? '',
            'contactAddress'=>$this->getPropertyList->address ?? '',
            'propertyClassification'=>$this->getPropertyList->getClass->class_name ?? '',
            'kgTin'=>$this->getPropertyList->owner_kgtin,
            'entryDate'=>date('d M, Y', strtotime($this->entry_date)),
            'assessmentNo'=>$this->assessment_no ?? '',
            'propertyAddress'=>$this->getPropertyList->address ?? '',
            'zone'=>$this->getPropertyList->zone_name ?? '',
            'phoneNo'=>$this->getPropertyList->owner_gsm ?? '',
            'assessValue'=>$this->assessed_value ?? 0,
            'chargeRate'=>$this->bill_rate ?? 0,
            'year'=>$this->year,
            'objection'=>$this->objection,
            'pavCode'=>$this->pav_code,
            'lgaName'=>$this->getLGA->lga_name ?? 'N/A',
            'billAmount'=>number_format($this->bill_amount ?? 0,2),
            'paidAmount'=>number_format($this->paid_amount ?? 0,2),
            'url'=>$this->url,
            'billedBy'=>$this->getBilledBy->name,
            'balance'=>number_format($this->bill_amount  - $this->paid_amount,2)
        ];
    }
}
