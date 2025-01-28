<?php

namespace App\Http\Resources;

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
        //return parent::toArray($request);
        $objection = Objection::where('bill_id', $this->id)->first();
        $objectionCount = !empty($objection) ? $objection->count() : 0;
        return [
          'approvedDate'=>date('d M, Y', strtotime($objection->date_approved)),
          'billId'=>$this->id ?? '',
          'assessmentNo'=>$this->assessment_no ?? '',
          'buildingCode'=>$this->building_code ?? '',
          'pavCode'=>$this->pav_code ?? '',
           'year'=>$this->year ?? '',
           'requestId'=> !empty($objection) ? $objection->request_id : '',
          'zoneName'=>$this->zone_name ?? '',
          'categoryName'=>$this->getPropertyClassification->class_name ?? '',
          'owner'=>$this->getPropertyList->owner_name ?? '',
          'billAmount'=>$this->bill_amount ?? '',
          'balance'=>$this->bill_amount ?? 0 - $this->paid_amount,
          'lgaName'=>$this->getLGA->lga_name ?? 'N/A',
          'url'=>$this->url ?? '',
          'assessValue'=>$this->assessed_value ?? '',
          'rate'=>$this->bill_rate ?? '',
          'occupancy'=>$this->occupancy ?? '',
          'objection'=>$objectionCount ?? 0,
        ];
    }
}
