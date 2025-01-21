<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ObjectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        //return parent::toArray($request);
        $statusLabel = null;
        switch ($this->status){
            case 0:
                $statusLabel = 'Pending';
                break;
            case 1:
                $statusLabel = 'Verified';
                break;
            case 2:
                $statusLabel = 'Declined';
                break;
            case 3:
                $statusLabel = 'Authorized';
                break;
            case 4:
                $statusLabel = 'Approved';
                break;
        }
        return [
          "billId"=>$this->bill_id,
          "buildingCode"=>$this->getBill->building_code ?? 'N/A',
          "billAmount"=> $this->getBill->bill_amount ?? 'N/A',
          "rate"=> $this->getBill->bill_rate ?? 0.0,
          "assessedValue"=> $this->getBill->assessed_value ?? 0.0,
          'lgaName'=>$this->getBill->getLGA->lga_name ?? 'N/A',
          "requestId"=>$this->request_id,
          "submittedBy"=>$this->getSubmittedBy->name,
          "reason"=>$this->reason,
          "reliefs"=>$this->getReliefs([$this->relief_ids]),
          "status"=>$statusLabel,
          "statusInt"=>$this->status,
          "date"=>date('d M, Y', strtotime($this->created_at)),

          "actionedBy"=>$this->getVerifiedBy->name ?? 'N/A',
          "dateActioned"=>!is_null($this->date_actioned) ? date('d M, Y', strtotime($this->date_actioned)) : '-',

          "authorizedBy"=>$this->getAuthorizedBy->name ?? '',
          "authorizedDate"=> !is_null($this->date_authorized) ? date('d M, Y', strtotime($this->date_authorized)) : '-',

          "approvedBy"=>$this->getApprovedBy->name ?? '',
          "approvedDate"=> !is_null($this->date_approved) ? date('d M, Y', strtotime($this->date_approved)) : '-',
        ];
    }
}
