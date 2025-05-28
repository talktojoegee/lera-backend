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
          "customerName"=>$this->payer_name,
          "reconciled"=>$this->reconciled,
          "assessmentNo"=>$this->assessment_no,
          "amount"=>number_format($this->credit,2),
          "reason"=>$this->reason,
          "entryDate"=>date('d/m/Y', strtotime($this->entry_date)),
          "valueDate"=>date('d/m/Y', strtotime($this->value_date)),
          "month"=>$this->month,
          "year"=>$this->year,
        ];
    }
}
