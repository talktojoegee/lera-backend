<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'issuedBy'=>$this->name ?? '',
            'idNo'=>$this->id_no ?? '',
            'assessmentNo'=>$this->assessment_no ?? '',
            'buildingCode'=>$this->building_code ?? '',
            'propertyName'=>$this->property_name ?? '',
            'amount'=>number_format($this->receiptAmount,2) ?? '',
            'status'=>$this->status,
            'receiptNo'=>$this->receipt_no ?? '',
            'entryDate'=> date('d/m/Y', strtotime($this->entry_date)) ?? '',
            'customerName'=>$this->customer_name ?? '',
            'branchName'=>$this->branch_name ?? '',
            'bankName'=>$this->bank_name ?? '',
            'kgTin'=>$this->kgtin ?? '',
            'reference'=>$this->reference ?? '',
            'email'=>$this->email ?? '',
            'proof'=>$this->proof_of_payment ?? '',
            'url'=>$this->url ?? ''
        ];
    }
}
