<?php

namespace App\Traits;

use App\Jobs\SendBillPaymentReceiptEmailJob;
use App\Models\BillPaymentLog;
use Illuminate\Support\Facades\Mail;

trait EmailTrait
{
    /**
     * Queue payment confirmation email (mail_queue connection).
     */
    protected function queueBillPaymentReceiptEmail(BillPaymentLog $paymentLog): void
    {
        SendBillPaymentReceiptEmailJob::dispatch($paymentLog->id);
    }

    /**
     * Send an email synchronously (legacy helpers).
     *
     * @return void
     */
    public function sendEmail(string $to, string $subject, string $view, array $data = [])
    {
        Mail::send($view, $data, function ($message) use ($to, $subject) {
            $message->to($to)
                ->subject($subject);
        });
    }
}
