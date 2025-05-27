<?php

namespace App\Http\Resources;

use App\Models\BillPaymentLog;
use App\Models\Objection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OutstandingBillResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $bbf = $this->balanceBroughtForward($this->year, $this->building_code);
        $objection = Objection::where('bill_id', $this->id)->first();
        $objectionCount = !empty($objection) ? $objection->count() : 0;
        $payment = BillPaymentLog::where('bill_master', $this->id)->sum('amount') ?? 0;
        return [
          'approvedDate'=> !empty($objection) ? date('d/m/Y', strtotime($objection->created_at)) : '',
          'approvalDate'=> isset($this->date_approved) ? date('d/m/Y', strtotime($this->date_approved)) : '',
          'returnedDate'=> isset($this->date_returned) ? date('d/m/Y', strtotime($this->date_returned)) : '',
          'billId'=>$this->id ?? '',
          'ward'=>$this->ward,
          'assessmentNo'=>$this->assessment_no ?? '',
          'buildingCode'=>$this->building_code ?? '',
          'pavCode'=>$this->pav_code ?? '',
           'year'=>$this->year ?? '',
           'requestId'=> !empty($objection) ? $objection->request_id : '',
          'zoneName'=>$this->zone_name ?? '',
          'categoryName'=>$this->getPropertyClassification->class_name ?? '',
          'owner'=>$this->getPropertyList->owner_name ?? '',
          'billAmount'=>ceil($this->bill_amount) ?? '',
          'balance'=>ceil($this->bill_amount  - $payment),
          //'balance'=>ceil($this->bill_amount  - $this->paid_amount),
            'paidAmount'=>$payment ?? 0,
           // 'paidAmount'=>$this->paid_amount ?? 0,

          'lgaName'=>$this->getLGA->lga_name ?? 'N/A',
          'url'=>$this->url ?? '',
          'assessValue'=>number_format($this->assessed_value,2) ?? '',
          'rate'=>$this->cr ?? '',
          'occupancy'=>$this->getChargeRate->occupancy ?? '',
          'objection'=>$objectionCount ?? 0,
          'special'=>$this->special,
          'status'=>$this->status,
          'printed'=>$this->printed ?? 0,
            'propertyUse'=>$this->property_use ?? '',
            'propertyName'=>$this->getPropertyList->property_name ?? '',
            'paid'=>$this->paid,
            'ownerName'=>$this->getPropertyList->owner_name ?? '',
            'contactAddress'=>$this->getPropertyList->address ?? '',
            'propertyClassification'=>$this->getPropertyList->getClass->class_name ?? '',
            'kgTin'=>$this->getPropertyList->owner_kgtin ?? '',
            'entryDate'=>date('d M, Y', strtotime($this->entry_date)),
            'propertyAddress'=>$this->getPropertyList->property_address ?? '',
            'ownerEmail'=>$this->getPropertyList->owner_email ?? '',
            'zone'=>$this->getPropertyList->sub_zone ?? '',
            'phoneNo'=>$this->getPropertyList->owner_gsm ?? '',
            //'assessValue'=>$this->assessed_value ?? 0,
            'chargeRate'=>$this->cr ?? 0,
            'statusInt'=>$this->status,
            'returned'=>$this->returned,
            'class'=>$this->getPropertyClassification->class_name,
            'age'=>$this->getPropertyList->building_age ?? '',
            'image'=>$this->getPropertyList->image ?? '',
            '//street'=>$this->getPropertyList->address ?? '',
            'reason'=>$this->return_reason ?? '',
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
