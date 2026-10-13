<?php

namespace App\Mail;

use App\Models\BillPaymentLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BillPaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BillPaymentLog $paymentLog,
        public string $receiptUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'LERA — Payment confirmation',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.receipt',
            with: [
                'name' => $this->paymentLog->customer_name ?: 'Customer',
                'amount' => (float) $this->paymentLog->amount,
                'receiptNo' => $this->paymentLog->receipt_no,
                'assessmentNo' => $this->paymentLog->assessment_no,
                'receiptUrl' => $this->receiptUrl,
                'payMode' => $this->paymentLog->pay_mode,
                'entryDate' => $this->paymentLog->entry_date,
            ],
        );
    }
}
