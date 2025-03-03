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
        return [
            'assessmentNo'=>$this->assessment_no ?? '',
            'buildingCode'=>$this->building_code ?? '',
            'year'=>$this->year ?? '',
            'pavCode'=>$this->pav_code ?? '',
            'zoneName'=>$this->zone_name ?? '',
            'categoryName'=>$this->getPropertyClassification->class_name ?? '',
            'occupancy'=>$this->getChargeRate->occupancy ?? '',
            'rate'=>$this->cr ?? 0,
            'assessValue'=>$this->assessed_value ?? 0,
            'billAmount'=>$this->bill_amount ?? 0,
            'ba'=>$this->ba ?? 0,
            'rr'=>$this->rr ?? 0,
            'dr'=>$this->dr ?? 0,
            'lr'=>$this->lr ?? 0,
            'la'=>$this->la ?? 0,
            'cr'=>$this->cr ?? 0,
            'br'=>$this->br ?? 0,
            'propertyUse'=>$this->property_use ?? '',
            'propertyName'=>$this->getPropertyList->property_name ?? '',
        ];
    }
}
