<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AreaOfficeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        //return parent::toArray($request);
        return [
          "areaOfficeId"=>$this->area_office_id,
          "areaName"=>$this->area_name,
          "lgaName"=>$this->getLga->lga_name ?? 'N/A',
        ];
    }
}
