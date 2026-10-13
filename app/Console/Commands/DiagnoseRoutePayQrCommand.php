<?php

namespace App\Console\Commands;

use App\Models\Lga;
use App\Services\RoutePayQrService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DiagnoseRoutePayQrCommand extends Command
{
    protected $signature = 'routepay:diagnose-qr
                            {--lga= : LGA/agency id (uses agency RoutePay settings when set)}
                            {--amount=1000 : Test amount in NGN}
                            {--try : Call RoutePay and attempt to generate a test QR}';

    protected $description = 'Diagnose RoutePay bill QR configuration (logo, OAuth, endpoints)';

    public function handle(RoutePayQrService $routePayQrService): int
    {
        $this->info('RoutePay QR diagnostics (APP_ENV='.config('app.env').')');

        $routePaySettings = null;
        $lgaId = $this->option('lga');
        if ($lgaId !== null && $lgaId !== '') {
            $agency = Lga::find((int) $lgaId);
            if ($agency === null) {
                $this->error("LGA id {$lgaId} not found.");

                return self::FAILURE;
            }
            $routePaySettings = $agency->routePaySettingsForQr();
            $this->line("Agency: {$agency->lga_name} (id {$agency->id})");
            if (! $routePaySettings['configured']) {
                $this->warn('Agency RoutePay is incomplete — set client ID, secret, auth URL, and API base URL in LERA admin.');
            }
            $this->line('Merchant logo: global (ROUTEPAY_MERCHANT_LOGO_PATH), shared by all agencies.');
        } else {
            $this->line('Using global .env / config/routepay.php (no --lga).');
        }

        $settings = $routePaySettings ?? [
            'auth_url' => config('routepay.auth_url'),
            'client_id' => config('routepay.client_id'),
            'client_secret' => config('routepay.client_secret'),
            'credential_source' => 'global_env',
            'base_url' => config('routepay.base_url'),
            'qr_generate_override_url' => config('routepay.qr_generate_override_url'),
            'qr_return_url' => config('routepay.qr_return_url'),
            'qr_duration_seconds' => config('routepay.qr_duration_seconds'),
            'qr_verify_me' => config('routepay.qr_verify_me'),
        ];

        $logoPath = (string) config('routepay.merchant_logo_path');
        $logoResolved = $this->resolveLogoPath($logoPath);

        $rows = [
            ['ROUTEPAY_QR_ENABLED', config('routepay.enabled') ? 'true' : 'false'],
            ['auth_url', $settings['auth_url'] ?? '—'],
            ['base_url', $settings['base_url'] ?? '—'],
            ['qr_generate_override_url', $settings['qr_generate_override_url'] ?? '(auto paths)'],
            ['qr_return_url', $settings['qr_return_url'] ?? '—'],
            ['client_id', $this->mask((string) ($settings['client_id'] ?? ''))],
            ['client_secret', $this->mask((string) ($settings['client_secret'] ?? ''))],
            ['credential_source', $settings['credential_source'] ?? ($routePaySettings !== null ? 'agency' : 'global_env')],
            ['agency_configured', isset($settings['configured']) ? ($settings['configured'] ? 'yes' : 'no') : 'n/a'],
            ['merchant_logo (configured)', $logoPath],
            ['merchant_logo (resolved)', $logoResolved ?? 'NOT READABLE'],
            ['GD extension', function_exists('imagecreatefrompng') ? 'yes' : 'no'],
        ];

        $this->table(['Setting', 'Value'], $rows);

        if ($logoResolved === null) {
            $this->error('Merchant logo not readable — QR will fail. Deploy public/assets/images/log.png and set ROUTEPAY_MERCHANT_LOGO_PATH.');
        }

        if (empty($settings['client_id']) || empty($settings['client_secret'])) {
            $this->error('Missing RoutePay client_id or client_secret (.env or agency payment gateway fields).');
        }

        if (config('app.env') === 'production' && str_contains(strtolower((string) ($settings['base_url'] ?? '')), 'apiqa')) {
            $this->warn('Production is using apiqa base URL. Set ROUTEPAY_BASE_URL and ROUTEPAY_AUTH_URL to RoutePay production hosts.');
        }

        $token = $routePayQrService->requestAccessToken($routePaySettings);
        if ($token === null) {
            $this->error('OAuth token request FAILED — check auth_url, client_id, client_secret, and outbound HTTPS from this server.');

            return self::FAILURE;
        }

        $this->info('OAuth token: OK');

        if (! $this->option('try')) {
            $this->line('Run with --try to call GenerateCustomizedQRDynamicPaymentLink.');

            return self::SUCCESS;
        }

        $amount = (float) $this->option('amount');
        $merchantRef = RoutePayQrService::merchantReferenceForBill('DIAG', date('Y'));
        $result = $routePayQrService->generateCustomizedQrDynamicPaymentLink(
            $token,
            'LERA QR Diagnostic',
            $amount,
            $merchantRef,
            (int) ($settings['qr_duration_seconds'] ?? 259200),
            (string) ($settings['qr_return_url'] ?? config('app.url')),
            (bool) ($settings['qr_verify_me'] ?? false),
            $logoPath,
            $routePaySettings,
        );

        if (! empty($result['qr_code_url'])) {
            $this->info('QR generation: OK');
            $this->line('qr_code_url: '.$result['qr_code_url']);
            if (! empty($result['transaction_reference'])) {
                $this->line('transaction_reference: '.$result['transaction_reference']);
            }

            return self::SUCCESS;
        }

        $this->error('QR generation FAILED — see storage/logs/laravel.log for RoutePay QR: FAILED entries.');
        $this->line('Enable ROUTEPAY_LOG_HTTP_RESPONSES=true temporarily for full API bodies.');

        return self::FAILURE;
    }

    protected function resolveLogoPath(string $configured): ?string
    {
        $candidates = array_filter([
            $configured,
            public_path('assets/images/log.png'),
            public_path('assets/images/logo.png'),
        ]);

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_readable($path)) {
                return realpath($path) ?: $path;
            }
        }

        if ($configured !== '' && Str::startsWith($configured, 'assets/')) {
            $path = public_path($configured);
            if (is_readable($path)) {
                return realpath($path) ?: $path;
            }
        }

        return null;
    }

    protected function mask(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '(empty)';
        }
        if (strlen($value) <= 4) {
            return str_repeat('*', strlen($value));
        }

        return str_repeat('*', max(4, strlen($value) - 4)).substr($value, -4);
    }
}
