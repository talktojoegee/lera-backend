<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id"=>$this->id,
          "details"=>$this->details,
          "customerName"=>$this->payer_name,
          "reconciled"=>$this->reconciled,
          "assessmentNo"=>$this->assessment_no,
          "amount"=>number_format($this->bank_amount,2),
          //"debit"=>number_format($this->debit,2),
          "billingAmount"=>number_format($this->billing_amount,2),
          "reason"=>$this->reason,
          "entryDate"=>date('d/m/Y', strtotime($this->entry_date)),
          "valueDate"=>date('d/m/Y', strtotime($this->value_date)),
          "month"=>$this->month,
          "year"=>$this->year,
          "propertyName"=>$this->property_name ?? '',
          "buildingCode"=>$this->building_code ?? '',
            "dcType"=>$this->dc_type,
            "matched"=>$this->matched,
            "billAssessmentNo"=>$this->bill_payment_assessment_no,
            "billBuildingCode"=>$this->bill_payment_building_code,
        ];
    }
}
