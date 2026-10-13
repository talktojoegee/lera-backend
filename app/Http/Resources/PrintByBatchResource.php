<?php

namespace App\Http\Resources;

use App\Models\PrintBillBatch;
use App\Models\PrintBillLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
        $batch = PrintBillBatch::where('batch_code', $this->batch_code)->first();
        $disk = (string) config('billing.batch_pdf_disk', 'local');
        $pdfReady = $batch
            && $batch->isReady()
            && Storage::disk($disk)->exists((string) $batch->pdf_path);

        return [
            'status' => $this->status,
            'id' => $this->id,
            'printedBy' => $this->getUser->name ?? '',
            'batchCode' => $this->batch_code,
            'counter' => $counter,
            'pdfStatus' => $batch->pdf_status ?? PrintBillBatch::STATUS_PENDING,
            'pdfReady' => $pdfReady,
            'date' => date('d M, Y h:ia', strtotime($this->created_at)),
        ];
    }
}
