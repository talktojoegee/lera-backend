<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $bbf = $this->balanceBroughtForward($this->year);
        return [
            'billId'=>$this->id,
            'paid'=>$this->paid,
            'ownerName'=>$this->getPropertyList->owner_name ?? '',
            'buildingCode'=>$this->building_code ?? '',
            'contactAddress'=>$this->getPropertyList->address ?? '',
            'propertyClassification'=>$this->getPropertyList->getClass->class_name ?? '',
            'kgTin'=>$this->getPropertyList->owner_kgtin,
            'entryDate'=>date('d M, Y', strtotime($this->entry_date)),
            'assessmentNo'=>$this->assessment_no ?? '',
            'propertyAddress'=>$this->getPropertyList->property_address ?? '',
            'ownerEmail'=>$this->getPropertyList->owner_email ?? '',
            'zone'=>$this->getPropertyList->sub_zone ?? '',
            'phoneNo'=>$this->getPropertyList->owner_gsm ?? '',
            'assessValue'=>$this->assessed_value ?? 0,
            //'assessValue'=>$this->assessed_value ?? 0,
            'chargeRate'=>$this->cr ?? 0,
            //'chargeRate'=>$this->bill_rate ?? 0,
            'year'=>$this->year,
            'objection'=>$this->objection,
            'statusInt'=>$this->status,
            'returned'=>$this->returned,
            'pavCode'=>$this->pav_code,
            'class'=>$this->getPropertyClassification->class_name,
            'occupancy'=>$this->property_use,
            'lgaName'=>$this->getLGA->lga_name ?? 'N/A',
            'billAmount'=>$this->bill_amount ?? 0,
            'paidAmount'=>$this->paid_amount ?? 0,
            'url'=>$this->url,
            'propertyUse'=>$this->property_use ?? '',
            'reason'=>$this->return_reason,
            'special'=>$this->special,
            'balance'=>number_format($this->bill_amount  - $this->paid_amount,2),
            'la'=>$this->la,
            'ba'=>$this->ba,
            'rr'=>$this->rr,
            'dr'=>$this->dr,
            'lr'=>$this->lr,
            'br'=>$this->br,
            'cr'=>$this->cr,
            'bbf'=>$bbf,
            'billedBy'=>$this->getBilledBy->name ?? '',
            'dateBilled'=>date('d M, Y h:ia', strtotime($this->created_at)),

            'approvedBy'=>$this->getApprovedBy->name ?? '',
            'dateApproved'=>date('d M, Y h:ia', strtotime($this->date_approved)),

            'authorizedBy'=>$this->getAuthorizedBy->name ?? '',
            'dateAuthorized'=>date('d M, Y h:ia', strtotime($this->date_authorized)),

            'verifiedBy'=>$this->getVerifiedBy->name ?? '',
            'dateVerified'=>date('d M, Y h:ia', strtotime($this->date_actioned)),

            'reviewedBy'=>$this->getReviewedBy->name ?? '',
            'dateReviewed'=>date('d M, Y h:ia', strtotime($this->date_reviewed)),

            'returnedBy'=>$this->getReturnedBy->name ?? '',
            'dateReturned'=>date('d M, Y h:ia', strtotime($this->date_returned)),


        ];
    }
}
