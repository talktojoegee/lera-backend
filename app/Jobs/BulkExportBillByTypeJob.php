<?php

namespace App\Jobs;

use App\Exports\BillingExport;
use App\Exports\PaidBillExport;
use App\Exports\PendingBillExport;
use App\Models\ExportRecord;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class BulkExportBillByTypeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 60;

    public int $timeout = 1200;

    public function __construct(
        public int $userId,
        public string $type,
        public string $path,
        public int $recordId,
    ) {}

    public function handle(): void
    {
        $memoryLimit = (string) config('billing.export_memory_limit', '2G');
        if ($memoryLimit !== '') {
            @ini_set('memory_limit', $memoryLimit);
        }

        $record = ExportRecord::query()->find($this->recordId);
        if ($record === null) {
            throw new \RuntimeException('Export record not found.');
        }

        $record->update([
            'status' => 'processing',
            'message' => 'Export is currently being generated...',
        ]);

        $export = match ($this->type) {
            'normal-outstanding', 'partly-paid-bills', 'paid-bills' => new PaidBillExport($this->userId, $this->type),
            'all-pending', 'pending-bills' => new PendingBillExport($this->userId, $this->type),
            default => new BillingExport($this->userId, $this->type),
        };

        $disk = (string) config('billing.export_disk', 'public');
        $stored = Excel::store($export, $this->path, $disk);

        if (! $stored || ! Storage::disk($disk)->exists($this->path)) {
            throw new \RuntimeException('Failed to write export file.');
        }

        $record->update([
            'status' => 'completed',
            'message' => 'Export completed successfully.',
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Bill export job failed.', [
            'export_id' => $this->recordId,
            'user_id' => $this->userId,
            'type' => $this->type,
            'error' => $exception->getMessage(),
        ]);

        ExportRecord::query()
            ->where('id', $this->recordId)
            ->update([
                'status' => 'failed',
                'message' => 'Export failed: '.$exception->getMessage(),
            ]);
    }
}
