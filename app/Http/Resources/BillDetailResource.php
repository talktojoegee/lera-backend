<?php

namespace App\Http\Resources;

use App\Models\EditBillLog;
use App\Models\PropertyClassification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class BillDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $bbf = $this->balanceBroughtForward($this->year, $this->building_code);
        $class = PropertyClassification::find($this->class_id);
        $logs = DB::table('edit_bill_logs')
        ->join('users', 'users.id','=', 'edit_bill_logs.edited_by')
        ->where('edit_bill_logs.bill_id', $this->id)
        ->select('edit_bill_logs.*', 'users.name')
        ->orderBy('edit_bill_logs.id', 'DESC')
        ->get();
        $payments = DB::table('bill_payment_logs')
            ->join('billings', 'billings.id','=', 'bill_payment_logs.bill_master')
            ->where('bill_payment_logs.bill_master', $this->id)
            ->select('bill_payment_logs.*')
            ->orderBy('bill_payment_logs.id', 'DESC')
            ->get();
        return [
            'billId'=>$this->id,
            'paid'=>$this->paid,
            'ownerName'=>$this->getPropertyList->owner_name ?? '',
            'buildingCode'=>$this->building_code ?? '',
            'contactAddress'=>$this->getPropertyList->address ?? '',
            'propertyClassification'=>$this->getPropertyList->getPropertyClassification->class_name ?? '',
            'propertyClass'=>$this->getPropertyList->getPropertyClassification->class_name ?? '',
            'kgTin'=>$this->getPropertyList->owner_kgtin ?? '',
            'entryDate'=>date('d M, Y', strtotime($this->entry_date)),
            'assessmentNo'=>$this->assessment_no ?? '',
            'propertyAddress'=>$this->getPropertyList->property_address ?? '',
            'ownerEmail'=>$this->getPropertyList->owner_email ?? '',
            'zone'=>$this->getPropertyList->sub_zone ?? '',
            'phoneNo'=>$this->getPropertyList->owner_gsm ?? '',
            'assessValue'=>$this->assessed_value ?? 0,
            //'assessValue'=>$this->assessed_value ?? 0,
            'chargeRate'=>$this->cr ?? 0,
            'propertyName'=>$this->getPropertyList->property_name ?? '',
            'year'=>$this->year,
            'objection'=>$this->objection,
            'statusInt'=>$this->status,
            'returned'=>$this->returned,
            'pavCode'=>$this->pav_code,
            'class'=> !empty($class) ? $class->class_name : '' , //$this->getPropertyList->getPropertyClassification->class_name,
            'occupancy'=>$this->property_use,
            'lgaName'=>$this->getLGA->lga_name ?? 'N/A',
            'billAmount'=>$this->bill_amount ?? 0,
            'paidAmount'=> $payments->sum('amount'),// $this->paid_amount ?? 0,
            'url'=>$this->url,
            'age'=>$this->getPropertyList->building_age ?? '',
            'image'=>$this->getPropertyList->image ?? '',
            'propertyUse'=>$this->property_use ?? '',
            '//street'=>$this->getPropertyList->address ?? '',
            'reason'=>$this->return_reason ?? '',
            'special'=>$this->special ?? 0,
            'balance'=>(($this->bill_amount + $bbf)  - $payments->sum('amount')),
            'la'=>$this->la,
            'ba'=>$this->ba,
            'rr'=>$this->rr,
            'dr'=>$this->dr,
            'lr'=>$this->lr,
            'br'=>$this->br,
            'cr'=>$this->cr,
            'bbf'=>$bbf,
            'assessmentYear'=>date('Y', strtotime(now())),
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
            'log'=>$logs,
            'payments'=>$payments,


        ];
    }
}
