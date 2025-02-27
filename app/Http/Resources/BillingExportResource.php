<?php

namespace App\Http\Resources;

use App\Models\Objection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingExportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /*
         * 'Assessment No', 'Building Code', 'Year', 'Billing Code',
            'Zone', 'Category', 'Occupancy', 'Charge Rate',
            'Assessed Mkt. Value(₦)', 'LUC(₦)'
         */
        return [
            //'approvedDate'=> !empty($objection) ? date('d M, Y', strtotime($objection->date_approved)) : '',
            //'billId'=>$this->id ?? '',
            'assessmentNo'=>$this->assessment_no ?? '',
            'buildingCode'=>$this->building_code ?? '',
            'year'=>$this->year ?? '',
            'pavCode'=>$this->pav_code ?? '',
            'zoneName'=>$this->zone_name ?? '',
            'categoryName'=>$this->getPropertyClassification->class_name ?? '',
            'occupancy'=>$this->getChargeRate->occupancy ?? '',
            'rate'=>$this->cr ?? '',
            'assessValue'=>$this->assessed_value ?? '',
            'billAmount'=>$this->bill_amount ?? '',
            'ba'=>$this->ba ?? 0,
            'rr'=>$this->rr ?? 0,
            'dr'=>$this->dr ?? 0,
            'lr'=>$this->lr ?? 0,
            'la'=>$this->la ?? 0,
            'cr'=>$this->cr ?? 0,
            'br'=>$this->br ?? 0,
            'propertyUse'=>$this->property_use ?? '',
            'propertyName'=>$this->getPropertyList->property_name ?? '',

            /*'requestId'=> !empty($objection) ? $objection->request_id : '',
            'owner'=>$this->getPropertyList->owner_name ?? '',
            'balance'=>$this->bill_amount ?? 0 - $this->paid_amount,
            'lgaName'=>$this->getLGA->lga_name ?? 'N/A',
            'url'=>$this->url ?? '',
            'objection'=>$objectionCount ?? 0,
            'special'=>$this->special,
            'status'=>$this->status,*/
        ];
    }
}
