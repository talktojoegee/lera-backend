<?php

namespace App\Http\Resources;

use App\Models\PrintBillLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrintByBatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $counter = PrintBillLog::where('batch_code', $this->batch_code)->count();
        return [
          "status"=>$this->status,
            "id"=>$this->id,
            "printedBy"=>$this->getUser->name ?? '',
            "batchCode"=>$this->batch_code,
            "counter"=>$counter,
            "date"=>date('d M, Y h:ia', strtotime($this->created_at))
        ];
    }
}
