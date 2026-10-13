<?php

namespace App\Console\Commands;

use App\Jobs\SendBillPaymentReceiptEmailJob;
use App\Models\Billing;
use App\Models\BillPaymentLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SmokeTestBillPaymentReceiptEmailCommand extends Command
{
    protected $signature = 'mail:smoke-payment-receipt
                            {email : Recipient email address}
                            {--sync : Send immediately (no queue worker)}
                            {--log-id= : Use an existing bill_payment_logs.id instead of creating a test row}';

    protected $description = 'Smoke-test LERA bill payment receipt email (Mailtrap SMTP + mail_queue job)';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address.');

            return self::FAILURE;
        }

        $this->info('Mail configuration');
        $this->table(
            ['Setting', 'Value'],
            [
                ['MAIL_MAILER', config('mail.default')],
                ['MAIL_HOST', config('mail.mailers.smtp.host')],
                ['MAIL_PORT', config('mail.mailers.smtp.port')],
                ['MAIL_FROM', config('mail.from.address')],
                ['QUEUE (job)', 'mail_queue'],
                ['Receipt URL base', config('app.bill_receipt_public_url', config('app.url'))],
            ]
        );

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->error('Database connection failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! DB::getSchemaBuilder()->hasTable('jobs')) {
            $this->warn('Table `jobs` is missing. Run: php artisan queue:table && php artisan migrate');
        }

        $logId = $this->option('log-id');
        if ($logId !== null && $logId !== '') {
            $paymentLog = BillPaymentLog::find((int) $logId);
            if ($paymentLog === null) {
                $this->error("bill_payment_logs id {$logId} not found.");

                return self::FAILURE;
            }
            $paymentLog->email = $email;
            $paymentLog->save();
            $this->line("Using existing payment log #{$paymentLog->id}");
        } else {
            $paymentLog = $this->createTestPaymentLog($email);
            if ($paymentLog === null) {
                return self::FAILURE;
            }
            $this->line("Created test payment log #{$paymentLog->id}");
        }

        if ($this->option('sync')) {
            $this->info('Sending synchronously (same code path as the queued job)...');
            try {
                (new SendBillPaymentReceiptEmailJob($paymentLog->id))->handle();
            } catch (\Throwable $e) {
                $this->error('Send failed: '.$e->getMessage());

                return self::FAILURE;
            }
            $this->info("Receipt email sent to {$email}. Check Mailtrap/inbox.");
        } else {
            SendBillPaymentReceiptEmailJob::dispatch($paymentLog->id);
            $pending = DB::table('jobs')->where('queue', 'mail_queue')->count();
            $this->info("Job queued on mail_queue (pending jobs: {$pending}).");
            $this->line('Process it with: php artisan queue:work mail_queue --once -v');
        }

        return self::SUCCESS;
    }

    protected function createTestPaymentLog(string $email): ?BillPaymentLog
    {
        $bill = Billing::query()->orderByDesc('id')->first();
        $billMasterId = $bill?->id ?? 1;

        $receiptNo = 'SMOKE-'.Str::upper(Str::random(8));

        return BillPaymentLog::create([
            'bill_master' => $billMasterId,
            'paid_by' => 1,
            'amount' => 1000,
            'receipt_no' => $receiptNo,
            'payment_code' => 'SMOKE-TEST',
            'assessment_no' => $bill?->assessment_no ?? 'SMOKE-TEST',
            'building_code' => $bill?->building_code ?? 'SMOKE',
            'bank_name' => 'Smoke Test',
            'branch_name' => 'Local',
            'pay_mode' => 'Smoke Test',
            'customer_name' => 'LERA Smoke Test',
            'email' => $email,
            'kgtin' => 'SMOKE-KGTIN',
            'entry_date' => now()->format('Y-m-d'),
            'trans_ref' => 'smoke-'.Str::random(12),
            'reference' => 'smoke-'.Str::random(12),
            'token' => 'smoke-test',
        ]);
    }
}
