<?php

namespace App\Http\Resources;

use App\Models\BillPaymentLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaidBillResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payment = BillPaymentLog::where('bill_master', $this->id)->sum('amount') ?? 0;
        return [
            'billId'=>$this->id,
            'assessmentNo'=>$this->assessment_no,
            'buildingCode'=>$this->building_code,
            'year'=>$this->year,
            'zoneName'=>$this->getPropertyList->zone_name ?? '',
            'categoryName'=>$this->getPropertyList->getPropertyClassification->class_name ?? '',
            'owner'=>$this->getPropertyList->owner_name ?? '',
            'billAmount'=>$this->bill_amount,
            'balance'=>($this->bill_amount  - $payment),
            'lgaName'=>$this->getLGA->lga_name ?? 'N/A',
            'url'=>$this->url,
            'payment'=>$payment
        ];
    }
}
