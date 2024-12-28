<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OutstandingBillResource extends JsonResource
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
          'billId'=>$this->id,
          'assessmentNo'=>$this->assessment_no,
          'buildingCode'=>$this->building_code,
           'year'=>$this->year,
          'zoneName'=>$this->getPropertyList->zone_name ?? '',
          'categoryName'=>$this->getPropertyList->getPropertyClassification->class_name ?? '',
          'owner'=>$this->getPropertyList->owner_name ?? '',
          'billAmount'=>$this->bill_amount,
          'balance'=>$this->bill_amount ?? 0 - $this->paid_amount,
          'lgaName'=>$this->getLGA->lga_name ?? 'N/A',
           'url'=>$this->url
        ];
    }
}
