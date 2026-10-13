<?php

namespace App\Console\Commands;

use App\Services\RoutePayWebhookService;
use Illuminate\Console\Command;

class FixRoutePayDonationCommand extends Command
{
    protected $signature = 'routepay:fix-donation
                            {transRef : bill_payment_logs.trans_ref / RoutePay transaction id}
                            {--bill-id= : billings.id when QR registration lookup fails}
                            {--assessment-no= : billings.assessment_no when QR registration lookup fails}';

    protected $description = 'Repair a legacy orphan RoutePay log (bill_master=0) onto the correct bill and update billings';

    public function handle(RoutePayWebhookService $webhookService): int
    {
        $transRef = trim((string) $this->argument('transRef'));
        if ($transRef === '') {
            $this->error('Transaction reference is required.');

            return self::FAILURE;
        }

        $billId = $this->option('bill-id') !== null ? (int) $this->option('bill-id') : null;
        $assessmentNo = trim((string) ($this->option('assessment-no') ?? ''));
        $assessmentNo = $assessmentNo !== '' ? $assessmentNo : null;

        $result = $webhookService->reclassifyDonationPayment($transRef, [], $billId, $assessmentNo);

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if ($result['processed'] ?? false) {
            $this->info(sprintf(
                'Bill %s (%s): paid_amount=%s, paid=%s',
                $result['bill_id'] ?? '?',
                $result['assessment_no'] ?? '?',
                $result['bill_paid_amount'] ?? '?',
                $result['bill_paid'] ?? '?'
            ));
        }

        return ($result['processed'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
