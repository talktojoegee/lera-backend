<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lga extends Model
{
    public const DEFAULT_AUTH_URL = 'https://authqa.routepay.com/connect/token';

    public const DEFAULT_BASE_URL = 'https://apiqa.routepay.com';

    public const DEFAULT_QR_VERIFY_ME = false;

    public const DEFAULT_QR_DURATION_SECONDS = 259200;

    public const DEFAULT_QR_RETURN_URL = '';

    /** Hostnames that must not be used as RoutePay returnUrl (no DNS / retired). */
    public const DEPRECATED_RETURN_URL_HOSTS = [
        'lera-land-admin.kslas.ng',
    ];

    public static function defaultWebhookUrl(): string
    {
        $path = trim((string) config('routepay.webhook_path', '/routepay/webhook'), '/');

        return rtrim((string) config('app.url'), '/').'/api/'.$path;
    }

    protected $fillable = [
        'lga_name',
        'payment_gateway_secret_key',
        'payment_gateway_client_id',
        'payment_gateway_auth_url',
        'payment_gateway_base_url',
        'payment_gateway_qr_generate_url',
        'payment_gateway_qr_return_url',
        'payment_gateway_qr_duration_seconds',
        'payment_gateway_qr_verify_me',
        'payment_gateway_qr_alternate_base_urls',
    ];

    protected $hidden = [
        'payment_gateway_secret_key',
    ];

    protected $casts = [
        'payment_gateway_qr_verify_me' => 'boolean',
        'payment_gateway_qr_duration_seconds' => 'integer',
    ];

    public function resolvePaymentGatewaySecretKey(): ?string
    {
        $key = trim((string) ($this->payment_gateway_secret_key ?? ''));

        return $key !== '' ? $key : null;
    }

    public function maskedPaymentGatewaySecretKey(): ?string
    {
        $key = $this->resolvePaymentGatewaySecretKey();
        if ($key === null) {
            return null;
        }

        if (strlen($key) <= 4) {
            return str_repeat('*', strlen($key));
        }

        return str_repeat('*', max(4, strlen($key) - 4)).substr($key, -4);
    }

    public function hasPaymentGatewayConfiguration(): bool
    {
        return trim((string) ($this->payment_gateway_client_id ?? '')) !== ''
            && $this->resolvePaymentGatewaySecretKey() !== null;
    }

    /**
     * Per-agency RoutePay settings for bill QR (OAuth, API hosts, return URL).
     * Merchant logo is always global — see config routepay.merchant_logo_path / ROUTEPAY_MERCHANT_LOGO_PATH.
     *
     * @return array{
     *   auth_url: string|null,
     *   client_id: string|null,
     *   client_secret: string|null,
     *   credential_source: string,
     *   base_url: string,
     *   qr_generate_override_url: string|null,
     *   qr_return_url: string,
     *   qr_duration_seconds: int,
     *   qr_verify_me: bool,
     *   qr_alternate_base_urls: list<string>,
     *   webhook_url: string,
     *   configured: bool
     * }
     */
    public static function isDeprecatedReturnUrl(string $url): bool
    {
        $host = strtolower((string) parse_url(trim($url), PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        return in_array($host, self::DEPRECATED_RETURN_URL_HOSTS, true);
    }

    /**
     * Base URL for RoutePay redirects when agency has not set a full return URL.
     * Per-agency payment_gateway_qr_return_url in the database takes priority.
     */
    public static function resolveRoutePayReturnUrl(?string $agencyStoredUrl = null): string
    {
        $agencyStoredUrl = rtrim(trim((string) $agencyStoredUrl), '/');
        if ($agencyStoredUrl !== '' && ! self::isDeprecatedReturnUrl($agencyStoredUrl)) {
            return $agencyStoredUrl;
        }

        $frontend = rtrim(trim((string) config('app.frontend_url', '')), '/');
        if ($frontend !== '' && ! self::isDeprecatedReturnUrl($frontend)) {
            return $frontend;
        }

        $fromConfig = rtrim(trim((string) config('routepay.qr_return_url', '')), '/');
        if ($fromConfig !== '' && ! self::isDeprecatedReturnUrl($fromConfig)) {
            return $fromConfig;
        }

        return '';
    }

    /**
     * Return URL sent to RoutePay (SetRequest + QR). Uses agency DB value as-is when set.
     */
    public static function routePayCheckoutReturnUrl(?string $agencyStoredUrl = null): string
    {
        $raw = rtrim(trim((string) $agencyStoredUrl), '/');
        if ($raw !== '' && ! self::isDeprecatedReturnUrl($raw)) {
            return $raw;
        }

        return self::routePayPaymentCompleteUrl(self::resolveRoutePayReturnUrl(null));
    }

    /** Default return URL when agency has only a base host (no path in DB). */
    public static function routePayPaymentCompleteUrl(?string $base = null): string
    {
        $base = rtrim($base ?? self::resolveRoutePayReturnUrl(), '/');
        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/');
        }

        return $base.'/payment/complete?source=routepay';
    }

    public function routePaySettingsForQr(): array
    {
        $returnUrl = self::routePayCheckoutReturnUrl($this->payment_gateway_qr_return_url);

        $duration = (int) ($this->payment_gateway_qr_duration_seconds ?? 0);
        if ($duration <= 0) {
            $duration = self::DEFAULT_QR_DURATION_SECONDS;
        }

        $authUrl = trim((string) ($this->payment_gateway_auth_url ?? ''));
        if ($authUrl === '') {
            $authUrl = self::DEFAULT_AUTH_URL;
        }

        $baseUrl = trim((string) ($this->payment_gateway_base_url ?? ''));
        if ($baseUrl === '') {
            $baseUrl = self::DEFAULT_BASE_URL;
        }

        $qrGenerateUrl = trim((string) ($this->payment_gateway_qr_generate_url ?? ''));

        $alternateUrls = trim((string) ($this->payment_gateway_qr_alternate_base_urls ?? ''));
        $alternateList = $alternateUrls !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $alternateUrls))))
            : [];

        $clientId = trim((string) ($this->payment_gateway_client_id ?? ''));
        $clientSecret = $this->resolvePaymentGatewaySecretKey();

        return [
            'auth_url' => $authUrl,
            'client_id' => $clientId !== '' ? $clientId : null,
            'client_secret' => $clientSecret,
            'credential_source' => 'agency',
            'base_url' => rtrim($baseUrl, '/'),
            'qr_generate_override_url' => $qrGenerateUrl !== '' ? $qrGenerateUrl : null,
            'qr_return_url' => $returnUrl,
            'qr_duration_seconds' => $duration,
            'qr_verify_me' => $this->payment_gateway_qr_verify_me ?? self::DEFAULT_QR_VERIFY_ME,
            'qr_alternate_base_urls' => $alternateList,
            'webhook_url' => self::defaultWebhookUrl(),
            'configured' => $this->hasPaymentGatewayConfiguration(),
        ];
    }

    public static function fetchAllLGAs()
    {
        return Lga::orderBy('lga_name', 'ASC')->get();
    }
}
