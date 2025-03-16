<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettlementReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "amount"=>$this->amount,
            "lgaName"=>$this->lga_name ?? '',
            "buildingCode"=>$this->building_code ?? '',
            "assessmentNo"=>$this->assessment_no ??'',
            "date"=> date('d/m/Y', strtotime($this->entry_date)),
        ];
    }
}
