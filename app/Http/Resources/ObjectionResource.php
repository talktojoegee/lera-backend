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
        $objectionLabel = null;
        switch ($this->status){
            case 0:
                $objectionLabel = 'Pending';
                break;
            case 1:
                $objectionLabel = 'Verified';
                break;
            case 2:
                $objectionLabel = 'Declined';
                break;
            case 3:
                $objectionLabel = 'Authorized';
                break;
            case 4:
                $objectionLabel = 'Approved';
                break;
        }
        $reliefIds = explode(',',$this->relief_ids );
        return [
          "billId"=>$this->bill_id,
          "buildingCode"=>$this->getBill->building_code ?? 'N/A',
          "billAmount"=> $this->getBill->bill_amount ?? 'N/A',
          "amountPaid"=> $this->getBill->paid_amount ?? 'N/A',
          "rate"=> $this->getBill->bill_rate ?? 0.0,
          "assessedValue"=> $this->getBill->assessed_value ?? 0.0,
          'lgaName'=>$this->getBill->getLGA->lga_name ?? 'N/A',
          "submittedBy"=>$this->getSubmittedBy->name,
          "reason"=>$this->reason,
          "year"=>$this->getBill->year,
          "reliefs"=>$this->getReliefs($reliefIds),
          "status"=>$statusLabel,
          "statusInt"=>$this->status,
          "date"=>date('d M, Y h:ia', strtotime($this->created_at)),
          "billEntryDate"=>date('d M, Y', strtotime($this->getBill->entry_date)),

          "assessmentNo"=>$this->getBill->assessment_no ?? 'N/A',
          "className"=>$this->getBill->getPropertyList->getPropertyClassification->class_name ?? 'N/A',
          "age"=>$this->getBill->getPropertyList->building_age ?? '-',
          "pavCode"=>$this->getBill->getPropertyList->pav_code ?? '-',
          "payStatus"=>$this->getBill->getPropertyList->pay_status ?? '-',
          "zone"=>$this->getBill->zone_name ?? 'N/A',

          "requestId"=>$this->request_id,
          "objectionAssessValue"=>$this->assess_value,
          "objectionRate"=>$this->rate,
          "objectionAmount"=>$this->luc_amount,
          "objectionStatus"=>$objectionLabel,

          "ownerName"=>$this->getBill->getPropertyList->owner_name ?? '-',
          "ownerEmail"=>$this->getBill->getPropertyList->owner_email ?? '-',
          "ownerMobileNo"=>$this->getBill->getPropertyList->owner_gsm ?? '-',
          "ownerKgTin"=>$this->getBill->getPropertyList->owner_kgtin ?? '-',

          "actionedBy"=>$this->getVerifiedBy->name ?? 'N/A',
          "dateActioned"=>!is_null($this->date_actioned) ? date('d M, Y', strtotime($this->date_actioned)) : '-',

          "authorizedBy"=>$this->getAuthorizedBy->name ?? '',
          "authorizedDate"=> !is_null($this->date_authorized) ? date('d M, Y', strtotime($this->date_authorized)) : '-',

          "approvedBy"=>$this->getApprovedBy->name ?? '',
          "approvedDate"=> !is_null($this->date_approved) ? date('d M, Y', strtotime($this->date_approved)) : '-',
        ];
    }
}
