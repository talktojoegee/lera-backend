<?php

namespace App\Jobs;

use App\Traits\BillingProcessTrait;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * ProcessBillingJob
 *
 * Queue job to process billing for properties
 * Uses BillingProcessTrait for all business logic
 */
class ProcessBillingJob implements ShouldQueue
{
    use BillingProcessTrait, Queueable;

    public $lgaId;

    public $year;

    public $billedBy;

    public $tries = 3;

    public $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct($lgaId, $year, $billedBy)
    {
        $this->lgaId = $lgaId;
        $this->year = $year;
        $this->billedBy = $billedBy;

        // Always use database queue (not QUEUE_CONNECTION=sync).
        $this->onConnection('database');
        $this->onQueue('data_sync_queue');
    }

    /**
     * Execute the job.
     * Delegates to BillingProcessTrait for all processing
     */
    public function handle(): void
    {
        try {
            Log::info('ProcessBillingJob: Starting', [
                'lga_id' => $this->lgaId,
                'year' => $this->year,
                'billed_by' => $this->billedBy,
            ]);

            // Use trait to process all billing
            $stats = $this->processBilling(
                $this->lgaId,
                $this->year,
                $this->billedBy,
                function ($message) {
                    Log::info('ProcessBillingJob: '.$message);
                }
            );

            Log::info('ProcessBillingJob: Completed successfully', $stats);

        } catch (\Exception $e) {
            Log::error('ProcessBillingJob: Job failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessBillingJob: Job failed permanently', [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'lga_id' => $this->lgaId,
            'year' => $this->year,
        ]);
    }
}
