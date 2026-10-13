<?php

namespace App\Jobs;

use App\Services\BatchBillPdfService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateBatchBillPdfJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 2;

    public int $uniqueFor = 3600;

    public function __construct(public string $batchCode)
    {
        $this->onQueue(config('billing.batch_pdf_queue', 'default'));
    }

    public function uniqueId(): string
    {
        return $this->batchCode;
    }

    public function handle(BatchBillPdfService $service): void
    {
        $service->generateAndStore($this->batchCode);
    }
}
