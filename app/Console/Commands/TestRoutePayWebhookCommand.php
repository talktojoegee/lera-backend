<?php

namespace App\Console\Commands;

use App\Models\Lga;
use App\Models\RoutePayQrRegistration;
use App\Services\RoutePayWebhookService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TestRoutePayWebhookCommand extends Command
{
    protected $signature = 'routepay:test-webhook
                            {--lga= : Agency id (for merchantId / signature secret)}
                            {--merchant-id= : merchantId / HMAC secret (overrides agency; e.g. xjIwnyINFXuHTEE)}
                            {--merchant-reference= : merchantReference from route_pay_qr_registrations}
                            {--amount=1000 : Payment amount (ignored when --payload-file is set)}
                            {--payload-file= : Replay exact RoutePay JSON file (nested event/data format)}
                            {--url= : Full webhook URL (default: APP_URL/api/routepay/webhook)}
                            {--send : POST the signed payload (requires network)}
                            {--event=payment : Query ?event= value}';

    protected $description = 'Build (and optionally send) a signed RoutePay webhook test request';

    public function handle(RoutePayWebhookService $webhookService): int
    {
        $payloadFileOption = trim((string) $this->option('payload-file'));
        $merchantId = trim((string) $this->option('merchant-id'));
        $agency = null;

        if ($merchantId === '' && $this->option('lga') !== null) {
            $agency = Lga::find((int) $this->option('lga'));
            if ($agency === null) {
                $this->error('Agency not found.');

                return self::FAILURE;
            }

            $merchantId = trim((string) $agency->payment_gateway_client_id);
        }

        if ($payloadFileOption !== '') {
            if (! is_file($payloadFileOption)) {
                $this->error('Payload file not found: '.$payloadFileOption);

                return self::FAILURE;
            }

            $jsonBody = (string) file_get_contents($payloadFileOption);
            $decoded = json_decode($jsonBody, true);
            if (! is_array($decoded)) {
                $this->error('Payload file is not valid JSON.');

                return self::FAILURE;
            }

            if ($merchantId === '') {
                $merchantId = (string) (
                    $decoded['merchantId']
                    ?? ($decoded['data']['merchantId'] ?? '')
                );
            }

            if ($merchantId === '') {
                $this->error('Pass --merchant-id= or include merchantId in the payload file.');

                return self::FAILURE;
            }
        } else {
            if ($agency === null) {
                $agency = Lga::find((int) ($this->option('lga') ?: 1));
            }
            if ($agency === null) {
                $this->error('Agency not found. Pass --lga= or --merchant-id=.');

                return self::FAILURE;
            }

            if ($merchantId === '') {
                $merchantId = trim((string) $agency->payment_gateway_client_id);
            }
            if ($merchantId === '') {
                $this->error('Agency has no payment_gateway_client_id. Pass --merchant-id=.');

                return self::FAILURE;
            }

            $merchantReference = trim((string) $this->option('merchant-reference'));
            if ($merchantReference === '') {
                $registration = RoutePayQrRegistration::query()
                    ->when($agency->id, fn ($q) => $q->where('lga_id', $agency->id))
                    ->orderByDesc('id')
                    ->first();
                $merchantReference = (string) ($registration?->merchant_reference ?? '');
            }

            if ($merchantReference === '') {
                $this->warn('No merchant reference — generate a bill PDF first, or pass --merchant-reference=');
                $merchantReference = 'TEST-'.bin2hex(random_bytes(8));
            }

            $body = [
                'merchantId' => $merchantId,
                'merchantReference' => $merchantReference,
                'transactionId' => 'TEST-TX-'.time(),
                'transactionReference' => 'TEST-TX-'.time(),
                'amount' => (float) $this->option('amount'),
                'paymentStatus' => 'successful',
                'status' => 'successful',
                'transactionDate' => now()->toIso8601String(),
            ];

            $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES);
        }
        $timestamp = (string) time();
        $payloadString = $merchantId.':'.$timestamp.':'.$jsonBody;
        $signature = hash_hmac('sha256', $payloadString, $merchantId);

        $baseUrl = rtrim((string) ($this->option('url') ?: config('app.url')), '/');
        $webhookUrl = $baseUrl.'/api/routepay/webhook?event='.$this->option('event');

        $this->info('RoutePay webhook test');
        $this->table(['Item', 'Value'], [
            ['POST URL', $webhookUrl],
            ['merchantId (HMAC)', $merchantId],
            ['x-merchant-id header', $merchantId.' (required for settlement when body has no merchantId)'],
            ['HMAC secret', 'same as merchantId (payment_gateway_client_id)'],
            ['merchantReference', $this->extractMerchantReferenceFromJson($jsonBody)],
            ['x-webhook-timestamp', $timestamp],
            ['x-webhook-signature', $signature],
        ]);

        $payloadFile = storage_path('app/routepay-webhook-test-payload.json');
        file_put_contents($payloadFile, $jsonBody);

        $parsedUrl = parse_url($webhookUrl);
        $path = ($parsedUrl['path'] ?? '/api/routepay/webhook').(isset($parsedUrl['query']) ? '?'.$parsedUrl['query'] : '');
        $testRequest = Request::create(
            $path,
            'POST',
            [],
            [],
            [],
            [
                'HTTP_HOST' => $parsedUrl['host'] ?? 'localhost',
                'HTTPS' => ($parsedUrl['scheme'] ?? 'https') === 'https' ? 'on' : 'off',
                'HTTP_X_MERCHANT_ID' => $merchantId,
                'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
                'HTTP_X_WEBHOOK_TIMESTAMP' => $timestamp,
                'CONTENT_TYPE' => 'application/json',
            ],
            $jsonBody,
        );
        $webhookService->logWebhookRequestForPostman($testRequest, 'artisan-test', $merchantId, true);

        $this->line('Postman replay file: storage/app/routepay-postman-last-request.json');
        $this->line('Also logged to laravel.log as: RoutePay webhook: Postman replay details');

        $this->newLine();
        $this->line('JSON body (must match signature byte-for-byte):');
        $this->line($jsonBody);
        $this->newLine();
        $this->line('curl command (uses payload file — do not escape quotes manually):');
        $this->line($this->buildCurl($webhookUrl, $payloadFile, $timestamp, $signature, $merchantId));

        if (! $this->option('send')) {
            $this->newLine();
            $this->line('Recommended: php artisan routepay:test-webhook --lga=1 --send');

            return self::SUCCESS;
        }

        if ($this->isRemoteWebhookUrl($webhookUrl)) {
            $this->warn('POSTing to a remote server — merchantReference must exist in route_pay_qr_registrations ON THAT SERVER.');
            $this->warn('Generate a bill PDF on production first, then on the VPS run:');
            $this->line('  php artisan tinker --execute="echo App\\Models\\RoutePayQrRegistration::latest()->value(\'merchant_reference\');"');
            $this->line('  php artisan routepay:test-webhook --lga=1 --merchant-reference=THAT_VALUE --send');
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'x-merchant-id' => $merchantId,
            'x-webhook-signature' => $signature,
            'x-webhook-timestamp' => $timestamp,
        ])->withBody($jsonBody, 'application/json')->post($webhookUrl);

        $this->newLine();
        $this->info('HTTP '.$response->status());
        $this->line($response->body());

        $decoded = json_decode($response->body(), true);
        if (is_array($decoded) && ($decoded['reason'] ?? '') === 'Unknown merchant reference') {
            $this->newLine();
            $this->warn('merchantReference is not in route_pay_qr_registrations on the target server.');
            $this->line('Create it by generating a bill PDF there, or test locally: --url=http://127.0.0.1:8000');
        }

        $internal = Request::create(
            '/api/routepay/webhook?event='.$this->option('event'),
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_MERCHANT_ID' => $merchantId,
                'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
                'HTTP_X_WEBHOOK_TIMESTAMP' => $timestamp,
            ],
            $jsonBody,
        );
        $internal->headers->set('Content-Type', 'application/json');
        $verified = $webhookService->verifySignature($internal, $merchantId);
        $this->line('Signature verifies in-app: '.($verified ? 'yes' : 'no'));

        return $response->successful() ? self::SUCCESS : self::FAILURE;
    }

    protected function isRemoteWebhookUrl(string $webhookUrl): bool
    {
        $host = parse_url($webhookUrl, PHP_URL_HOST);

        return ! in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }

    protected function extractMerchantReferenceFromJson(string $jsonBody): string
    {
        $decoded = json_decode($jsonBody, true);
        if (! is_array($decoded)) {
            return '';
        }

        return (string) (
            $decoded['merchantReference']
            ?? ($decoded['data']['merchantReference'] ?? '')
        );
    }

    protected function buildCurl(string $url, string $payloadFile, string $timestamp, string $signature, string $merchantId): string
    {
        return <<<CURL
curl -X POST '{$url}' \\
  -H 'Content-Type: application/json' \\
  -H 'Accept: application/json' \\
  -H 'x-merchant-id: {$merchantId}' \\
  -H 'x-webhook-signature: {$signature}' \\
  -H 'x-webhook-timestamp: {$timestamp}' \\
  --data-binary @{$payloadFile}
CURL;
    }
}
