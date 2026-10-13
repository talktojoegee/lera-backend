<?php

namespace App\Console\Commands;

use App\Services\BatchBillPdfService;
use Illuminate\Console\Command;

class RegenerateBatchBillPdfCommand extends Command
{
    protected $signature = 'bills:regenerate-batch-pdf {batchCode : Print batch code} {--sync : Generate immediately instead of queueing}';

    protected $description = 'Regenerate a stored batch bill PDF (fixes missing QR images)';

    public function handle(BatchBillPdfService $service): int
    {
        $batchCode = (string) $this->argument('batchCode');

        if ($this->option('sync')) {
            $service->regenerate($batchCode);
            $this->info("Regenerating batch {$batchCode} synchronously...");
            $service->generateAndStore($batchCode);
            $this->info('Done.');

            return self::SUCCESS;
        }

        $service->regenerate($batchCode);
        $this->info("Queued PDF regeneration for batch {$batchCode}.");

        return self::SUCCESS;
    }
}
