<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PAVResource extends JsonResource
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
            "id"=>$this->id,
            'pavCode' => $this->pav_code,
            'zones' => $this->zones,
            'propertyType' => $this->property_type ?? '',
            'annualDue' => $this->annual_due ?? 0,
            'createdAt' => $this->created_at?->toDateTimeString() ?? null,
            'updatedAt' => $this->updated_at?->toDateTimeString() ?? null,
        ];
    }
}
