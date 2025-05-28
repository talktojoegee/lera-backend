<?php

namespace App\Http\Resources;

use App\Models\Reconciliation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return
            [
                "month"=>$this->month,
                "year"=>$this->year,
                "uuid"=>$this->uuid,
                "status"=>$this->confirmed,
                "createdAt"=>date('d/m/Y', strtotime($this->created_at)),
                "user"=>User::find($this->user_id)->name ?? null,
                "count"=>Reconciliation::where("master_id", $this->id)->count() ?? 0,
            ];
    }
}
