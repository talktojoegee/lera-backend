<?php

namespace App\Console\Commands;

use App\Models\BillPaymentLog;
use App\Models\Lga;
use App\Models\RoutePayQrRegistration;
use App\Services\RoutePayQrService;
use App\Services\RoutePayWebhookService;
use Illuminate\Console\Command;

class ReconcileRoutePayPaymentCommand extends Command
{
    protected $signature = 'routepay:reconcile
                            {transaction : RoutePay transactionId / transactionReference}
                            {--lga= : Agency id for RoutePay credentials}
                            {--merchant-reference= : merchantReference from route_pay_qr_registrations}
                            {--remove-donation-log : Delete mistaken donation log with this trans_ref before recording}';

    protected $description = 'Fetch a RoutePay transaction and record the bill payment if successful (missed webhook recovery)';

    public function handle(RoutePayQrService $qrService, RoutePayWebhookService $webhookService): int
    {
        $transaction = trim((string) $this->argument('transaction'));
        if ($transaction === '') {
            $this->error('Transaction reference is required.');

            return self::FAILURE;
        }

        $merchantReference = trim((string) $this->option('merchant-reference'));

        $registration = RoutePayQrRegistration::query()
            ->when($merchantReference !== '', fn ($q) => $q->where('merchant_reference', $merchantReference))
            ->where(function ($q) use ($transaction) {
                $q->where('transaction_reference', $transaction)
                    ->orWhere('merchant_reference', $transaction);
            })
            ->orderByDesc('id')
            ->first();

        if ($registration === null && $merchantReference !== '') {
            $registration = RoutePayQrRegistration::where('merchant_reference', $merchantReference)
                ->orderByDesc('id')
                ->first();
        }

        $agency = null;
        if ($this->option('lga') !== null) {
            $agency = Lga::find((int) $this->option('lga'));
        } elseif ($registration?->lga_id) {
            $agency = Lga::find($registration->lga_id);
        } else {
            $agency = Lga::query()->whereNotNull('payment_gateway_client_id')->first();
        }

        if ($agency === null) {
            $this->error('No agency with RoutePay credentials found. Pass --lga=ID.');

            return self::FAILURE;
        }

        $settings = $agency->routePaySettingsForQr();
        $tx = $qrService->fetchPaymentTransaction($transaction, $settings);

        if ($tx === null) {
            $this->error('Could not fetch transaction from RoutePay. Check transaction id and agency credentials.');

            return self::FAILURE;
        }

        $this->line(json_encode($tx, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $merchantReference = $merchantReference !== ''
            ? $merchantReference
            : (string) ($tx['merchantReference'] ?? $tx['MerchantReference'] ?? $registration?->merchant_reference ?? '');

        $grossAmount = (float) ($tx['amount'] ?? $tx['totalAmount'] ?? $tx['transactionAmount'] ?? $tx['settledAmount'] ?? 0);

        $payload = array_merge($tx, [
            'merchantId' => (string) ($tx['merchantId'] ?? $settings['client_id'] ?? ''),
            'merchantReference' => $merchantReference,
            'transactionId' => (string) ($tx['transactionId'] ?? $tx['transactionReference'] ?? $transaction),
            'transactionReference' => (string) ($tx['transactionReference'] ?? $tx['transactionId'] ?? $transaction),
            'amount' => $grossAmount,
            'settledAmount' => $grossAmount,
            'paymentStatus' => $tx['paymentStatus'] ?? $tx['status'] ?? $tx['Status'] ?? 'Successful',
            'status' => $tx['status'] ?? $tx['Status'] ?? $tx['paymentStatus'] ?? 'Successful',
        ]);

        if ($registration) {
            $this->table(['Registration', 'Value'], [
                ['id', $registration->id],
                ['bill_id', $registration->bill_id],
                ['assessment_no', $registration->assessment_no],
                ['merchant_reference', $registration->merchant_reference],
                ['amount_expected', $registration->amount_expected],
            ]);
        }

        $reclassify = $webhookService->reclassifyDonationPayment($transaction, $payload);
        if ($reclassify['processed'] ?? false) {
            $this->info('Reclassified existing donation log to bill payment (billings updated).');
            $this->line(json_encode($reclassify, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($this->option('remove-donation-log')) {
            $deleted = BillPaymentLog::where('trans_ref', $transaction)
                ->where(function ($q) {
                    $q->where('bill_master', 0)->orWhere('assessment_no', 'DONATION');
                })
                ->delete();
            if ($deleted > 0) {
                $this->warn("Removed {$deleted} mistaken donation log(s) for trans_ref {$transaction}.");
            }
        }

        $result = $webhookService->processVerifiedPaymentPayload($payload);

        $this->info('HTTP-style result status: '.$result['status']);
        $this->line(json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $processed = (bool) ($result['body']['processed'] ?? false);
        $reason = (string) ($result['body']['reason'] ?? '');

        if ($processed && $reason === 'Already processed') {
            $this->warn('Transaction already recorded for this bill. No changes made.');
        }

        return $processed ? self::SUCCESS : self::FAILURE;
    }
}
