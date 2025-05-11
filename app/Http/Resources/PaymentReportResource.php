<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
          "date"=>$this->entry_date,
          "buildingCode"=>$this->building_code,
          "assessmentNo"=>$this->assessment_no,
          "receiptNo"=>$this->receipt_no ?? '',
          "transRef"=>$this->trans_ref ?? '',
          "ownerName"=>$this->customer_name ?? '',
          "amount"=>$this->amount,
        ];
    }
}
