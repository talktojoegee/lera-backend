<?php

namespace App\Jobs;

use App\Exports\BillingExport;
use App\Exports\PaidBillExport;
use App\Models\ExportRecord;
use Throwable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class BulkExportBillByTypeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $backoff = 60;
    public $timeout = 1200;

    protected $userId;
    protected $type;
    protected $path;
    protected $recordId;

    /**
     * Create a new job instance.
     */
    public function __construct($userId, $type, $path, $recordId)
    {
        $this->userId = $userId;
        $this->type = $type;
        $this->path = $path;
        $this->recordId = $recordId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Increase memory limit for large exports
        ini_set('memory_limit', '2G');

        ExportRecord::where('id', $this->recordId)
            ->update([
                'status' => 'processing',
                'message' => 'Export is currently being generated...',
            ]);

        $export = in_array($this->type, ['normal-outstanding', 'partly-paid-bills'], true)
            ? new PaidBillExport($this->userId, $this->type)
            : new BillingExport($this->userId, $this->type);

        $action = Excel::store(
            $export,
            $this->path,
            'public'
        );

        if ($action) {
            ExportRecord::where('id', $this->recordId)
                ->update([
                    'status' => 'completed',
                    'message' => 'Export completed successfully.',
                ]);
        }

    }

    public function failed(Throwable $exception): void
    {
        ExportRecord::where('id', $this->recordId)
            ->update([
                'status' => 'failed',
                'message' => 'Export failed. Please retry.',
            ]);
    }
}
