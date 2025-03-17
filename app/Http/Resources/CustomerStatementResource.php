<?php

namespace App\Http\Resources;

use App\Models\Billing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerStatementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $bill = Billing::find($this->bill_master);
        $narration = "N/A";
        if(!empty($bill)){
            $pDate = date('d M, Y', strtotime($this->entry_date));
            $narration = "LUC: {$bill->bill_amount}, Payment Date: {$pDate}, BUEAREU OF LANDS, Mode of Payment: {$this->pay_mode},
            Assessment No.: {$bill->assessment_no}, Transaction Reference: {$bill->trans_ref}, Year: {$bill->year}";
        }

        return [
          "date"=>date('d M, Y', strtotime($this->entry_date)),
          "amount"=>$this->amount,
            "paymentMode"=>$this->pay_mode ?? '',
            "receipt"=>$this->receipt_no ?? '',
            "narration"=>$narration,
        ];
    }
}
