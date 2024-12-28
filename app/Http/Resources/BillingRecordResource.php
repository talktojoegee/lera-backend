<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
          'id'=>$this->id,
          'buildingCode'=>$this->building_code,
          'assessmentNo'=>$this->assessment_no,
          'assessedValue'=>$this->assessed_value ?? 0,
          'billAmount'=>$this->bill_amount,
          'date'=>$this->entry_date,
          'paid'=>$this->paid,
          'paidAmount'=>$this->paid_amount,
          'objection'=>$this->objection,
          'year'=>$this->year,
          'lgaName'=>$this->getLGA->lga_name ?? 'N/A',
        ];
    }
}
