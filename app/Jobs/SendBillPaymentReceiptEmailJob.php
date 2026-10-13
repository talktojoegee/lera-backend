<?php

namespace App\Jobs;

use App\Mail\BillPaymentReceiptMail;
use App\Models\BillPaymentLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBillPaymentReceiptEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $billPaymentLogId,
    ) {
        $this->onConnection('mail_queue');
    }

    public function handle(): void
    {
        $paymentLog = BillPaymentLog::find($this->billPaymentLogId);
        if ($paymentLog === null) {
            Log::warning('Bill payment receipt email: payment log not found.', [
                'bill_payment_log_id' => $this->billPaymentLogId,
            ]);

            return;
        }

        $email = trim((string) $paymentLog->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::debug('Bill payment receipt email: skipped — no valid email.', [
                'bill_payment_log_id' => $paymentLog->id,
            ]);

            return;
        }

        if (preg_match('/^placeholder@/i', $email)) {
            Log::debug('Bill payment receipt email: skipped — placeholder address.', [
                'bill_payment_log_id' => $paymentLog->id,
            ]);

            return;
        }

        $receiptUrl = rtrim((string) config('app.bill_receipt_public_url', config('app.url')), '/')
            .'/receipt/'.urlencode((string) $paymentLog->receipt_no);

        try {
            Mail::to($email)->send(new BillPaymentReceiptMail($paymentLog, $receiptUrl));
            Log::info('Bill payment receipt email sent.', [
                'bill_payment_log_id' => $paymentLog->id,
                'receipt_no' => $paymentLog->receipt_no,
            ]);
        } catch (\Throwable $e) {
            Log::error('Bill payment receipt email failed.', [
                'bill_payment_log_id' => $paymentLog->id,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
