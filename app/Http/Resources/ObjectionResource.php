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
          "billAmount"=>number_format($this->getBill->bill_amount,2) ?? 'N/A',
          'lgaName'=>$this->getBill->getLGA->lga_name ?? 'N/A',
          "requestId"=>$this->request_id,
          "submittedBy"=>$this->getSubmittedBy->name,
          "reason"=>$this->reason,
          "reliefs"=>$this->getReliefs([$this->relief_ids]),
          "status"=>$statusLabel,
          "statusInt"=>$this->status,
          "actionedBy"=>$this->actioned_by,
          "dateActioned"=>$this->date_actioned,
          "date"=>date('d M, Y', strtotime($this->created_at)),
        ];
    }
}
