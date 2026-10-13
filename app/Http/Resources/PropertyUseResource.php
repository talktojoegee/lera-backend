<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyUseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->property_type ?? $this->property_use ?? '';

        return [
            'id' => $type,
            'propertyUse' => $type,
            'label' => $type !== '' ? ucwords(str_replace('_', ' ', $type)) : '',
        ];
    }
}
