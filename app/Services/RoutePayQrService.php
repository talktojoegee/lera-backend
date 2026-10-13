<?php

namespace App\Services;

use App\Models\RoutePayQrRegistration;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RoutePayQrService
{
    /** @var array<string, string|null> */
    private array $accessTokenCache = [];

    /**
     * RoutePay merchant reference format (40 chars, echoed on ?event=payment webhooks):
     * LERA-{assessmentToken13}-{YYYYMMDDHHmmss}-{seq6}
     * Example: LERA-6a04480f62789-20260611085200-001536
     */
    public static function merchantReferenceForBill(string $assessmentNo, mixed $year): string
    {
        $assessmentNo = trim($assessmentNo);
        $sanitized = strtoupper(preg_replace('/[^A-Z0-9]/', '', $assessmentNo) ?? '');
        $seed = $sanitized !== '' && strcasecmp($sanitized, 'NA') !== 0
            ? $sanitized.'|'.(string) ($year ?? '')
            : uniqid('lera', true).'|'.(string) ($year ?? '');

        $token = strtolower(substr(hash('sha256', $seed), 0, 13));
        $timestamp = date('YmdHis');
        $sequence = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return self::formatLeraMerchantReference($token, $timestamp, $sequence);
    }

    public static function formatLeraMerchantReference(string $token, string $timestamp, string $sequence): string
    {
        $token = strtolower(substr(preg_replace('/[^a-f0-9]/', '', $token) ?? '', 0, 13));
        $token = str_pad($token, 13, '0', STR_PAD_RIGHT);
        $timestamp = preg_replace('/\D/', '', $timestamp) ?? '';
        $timestamp = str_pad(substr($timestamp, 0, 14), 14, '0', STR_PAD_LEFT);
        $sequence = str_pad(substr(preg_replace('/\D/', '', $sequence) ?? '', -6), 6, '0', STR_PAD_LEFT);

        $reference = 'LERA-'.$token.'-'.$timestamp.'-'.$sequence;
        $max = max(40, min(64, (int) config('routepay.merchant_reference_max_length', 40)));

        return substr($reference, 0, $max);
    }

    public static function isLeraMerchantReference(string $reference): bool
    {
        return (bool) preg_match('/^LERA-[a-f0-9]{13}-\d{14}-\d{6}$/i', trim($reference));
    }

    /**
     * Resolve merchant logo to a readable absolute path. Relative values like
     * "public/assets/images/log.png" depend on PHP's cwd and often fail under FPM;
     * we map them to base_path() / public_path().
     */
    protected function resolveMerchantLogoPath(string $configured): ?string
    {
        $candidates = [];

        if ($configured !== '') {
            $candidates[] = $configured;

            if ($this->isRelativePath($configured)) {
                $candidates[] = base_path(trim(str_replace(['\\'], '/', $configured), '/'));
                $normalized = str_replace(['\\'], '/', $configured);
                if (Str::startsWith($normalized, 'public/')) {
                    $candidates[] = public_path(Str::after($normalized, 'public/'));
                }
                $candidates[] = public_path(trim($normalized, '/'));
            }
            $candidates[] = public_path('assets/images/'.basename(str_replace(['\\'], '/', $configured)));
        }

        $candidates[] = public_path('assets/images/logo.png');
        $candidates[] = public_path('assets/images/log.png');
        $candidates[] = public_path('assets/images/kgirs.png');

        foreach (array_unique(array_filter($candidates)) as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }
            if (is_readable($path)) {
                $real = realpath($path);

                return $real !== false ? $real : $path;
            }
        }

        return null;
    }

    protected function isRelativePath(string $path): bool
    {
        if ($path === '') {
            return true;
        }
        if ($path[0] === '/' || $path[0] === '\\') {
            return false;
        }
        if (strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':' && ($path[2] === '\\' || $path[2] === '/')) {
            return false;
        }

        return true;
    }

    protected function shouldLogRoutePayHttpResponses(): bool
    {
        return (bool) config('routepay.log_http_responses', false);
    }

    protected function logResponseBodyForSupport(string $body): string
    {
        $max = max(512, (int) config('routepay.log_response_body_max_chars', 16384));
        if (mb_strlen($body) <= $max) {
            return $body;
        }

        return mb_substr($body, 0, $max).'…[truncated]';
    }

    /**
     * Full RoutePay QR endpoint response for vendor support (enable ROUTEPAY_LOG_HTTP_RESPONSES).
     */
    protected function logRoutePayQrHttpResponse(string $requestUrl, Response $response): void
    {
        if (! $this->shouldLogRoutePayHttpResponses()) {
            return;
        }

        Log::info('RoutePay QR: raw HTTP response (for RoutePay support)', [
            'request_url' => $requestUrl,
            'http_status' => $response->status(),
            'headers' => $response->headers(),
            'response_body' => $this->logResponseBodyForSupport($response->body()),
            'full_response' => [
                'status' => $response->status(),
                'reason' => $response->getReasonPhrase() ?? 'N/A',
                'headers' => $response->headers(),
                'body_truncated' => mb_strlen($response->body()) > (int) config('routepay.log_response_body_max_chars', 16384),
                'body_size' => mb_strlen($response->body()).' bytes',
            ],
        ]);
    }

    /**
     * OAuth token response for vendor support; access_token is never logged in plain text.
     */
    protected function logRoutePayOAuthHttpResponse(string $authUrl, Response $response): void
    {
        if (! $this->shouldLogRoutePayHttpResponses()) {
            return;
        }

        $decoded = json_decode($response->body(), true);
        if (is_array($decoded)) {
            if (array_key_exists('access_token', $decoded)) {
                $decoded['access_token'] = '[REDACTED]';
            }
            if (array_key_exists('refresh_token', $decoded)) {
                $decoded['refresh_token'] = '[REDACTED]';
            }
            Log::info('RoutePay QR: OAuth token HTTP response (for RoutePay support)', [
                'request_url' => $authUrl,
                'http_status' => $response->status(),
                'headers' => $response->headers(),
                'response_json' => $decoded,
                'full_response' => [
                    'status' => $response->status(),
                    'reason' => $response->getReasonPhrase() ?? 'N/A',
                    'headers' => $response->headers(),
                    'body_size' => mb_strlen($response->body()).' bytes',
                ],
            ]);

            return;
        }

        Log::info('RoutePay QR: OAuth token HTTP response (for RoutePay support)', [
            'request_url' => $authUrl,
            'http_status' => $response->status(),
            'headers' => $response->headers(),
            'response_body' => $this->logResponseBodyForSupport($response->body()),
            'full_response' => [
                'status' => $response->status(),
                'reason' => $response->getReasonPhrase() ?? 'N/A',
                'headers' => $response->headers(),
                'body_truncated' => mb_strlen($response->body()) > (int) config('routepay.log_response_body_max_chars', 16384),
                'body_size' => mb_strlen($response->body()).' bytes',
            ],
        ]);
    }

    /**
     * Detect marketing-site HTML returned instead of JSON (wrong ROUTEPAY_BASE_URL).
     */
    protected function responseBodyLooksLikeHtml(string $body): bool
    {
        $trim = ltrim($body);

        return str_starts_with($trim, '<!DOCTYPE')
            || str_starts_with(strtolower($trim), '<html')
            || str_contains(substr($trim, 0, 500), '<html');
    }

    /**
     * Candidate POST URLs — sandbox apiqa redirects /api/v1/* (301); use /payment/api/v1/* first. Extra hosts via ROUTEPAY_QR_ALTERNATE_BASE_URLS.
     *
     * @return list<string>
     */
    protected function qrGenerateCandidateUrls(
        string $primaryBaseUrl,
        ?string $overrideUrl = null,
        array $alternateBaseUrls = [],
    ): array {
        $override = $overrideUrl ?? config('routepay.qr_generate_override_url');
        if (is_string($override) && $override !== '') {
            return [rtrim($override, '/')];
        }

        $primaryBaseUrl = rtrim($primaryBaseUrl, '/');
        $paths = config('routepay.qr_endpoint_paths', [
            '/api/v1/QRCode/GenerateCustomizedQRDynamicPaymentLink',
        ]);

        if (! is_array($paths) || $paths === []) {
            $paths = ['/api/v1/QRCode/GenerateCustomizedQRDynamicPaymentLink'];
        }

        $bases = [$primaryBaseUrl];
        $extras = $alternateBaseUrls !== [] ? $alternateBaseUrls : config('routepay.qr_alternate_base_urls', []);
        if (is_array($extras)) {
            foreach ($extras as $b) {
                if (is_string($b) && trim($b) !== '') {
                    $bases[] = rtrim(trim($b), '/');
                }
            }
        }

        $bases = array_values(array_unique($bases));
        $urls = [];
        foreach ($bases as $base) {
            foreach ($paths as $path) {
                $urls[] = $base.'/'.ltrim((string) $path, '/');
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * RoutePay validates MerchantReference length (sandbox: max 40). LERA refs are exactly 40 chars.
     */
    public static function normalizeMerchantReference(string $reference): string
    {
        $reference = trim($reference);
        if ($reference === '') {
            return self::merchantReferenceForBill('NA', date('Y'));
        }

        if (self::isLeraMerchantReference($reference)) {
            return $reference;
        }

        $max = max(40, min(64, (int) config('routepay.merchant_reference_max_length', 40)));
        if (mb_strlen($reference) <= $max) {
            return $reference;
        }

        return substr(hash('sha256', $reference), 0, $max);
    }

    /**
     * RoutePay DynamicName: use a real label when property_name is null, empty, or placeholder (N/A).
     */
    public static function billDynamicDisplayName(?string $propertyName, string $assessmentNo): string
    {
        $assessmentNo = trim($assessmentNo);
        $prop = trim((string) $propertyName);
        $propIsPlaceholder = $prop === ''
            || strcasecmp($prop, 'N/A') === 0
            || strcasecmp($prop, 'NA') === 0;

        if ($propIsPlaceholder) {
            return 'LERA Bill Payment';
        }

        $label = 'LERA — '.$prop.($assessmentNo !== '' ? ' — '.$assessmentNo : '');

        return mb_substr($label, 0, 120);
    }

    /**
     * @return array{qr_code_url: string|null, transaction_reference: string|null, raw: array|null, api_rejection: bool}
     */
    protected function parseSuccessfulQrJsonResponse(string $rawBody, string $requestUrl): array
    {
        $empty = static fn (): array => [
            'qr_code_url' => null,
            'transaction_reference' => null,
            'merchant_reference' => null,
            'raw' => null,
            'api_rejection' => false,
        ];

        if ($this->responseBodyLooksLikeHtml($rawBody)) {
            Log::debug('RoutePay QR: skipping HTML body for candidate URL.', ['request_url' => $requestUrl]);

            return $empty();
        }

        $decoded = json_decode($rawBody, true);
        $json = is_array($decoded) ? $decoded : null;

        if (! is_array($json)) {
            Log::debug('RoutePay QR: candidate response is not valid JSON.', [
                'request_url' => $requestUrl,
                'body_preview' => mb_substr($rawBody, 0, 400),
            ]);

            return $empty();
        }

        if (! empty($json['isSuccess'])) {
            $value = $json['value'] ?? null;
            $qrUrl = is_array($value) ? ($value['qrCode'] ?? null) : null;
            $txRef = is_array($value)
                ? ($value['transactionReference'] ?? $value['transactionId'] ?? null)
                : null;
            $merchantRef = is_array($value)
                ? ($value['merchantReference'] ?? $value['MerchantReference'] ?? null)
                : null;

            return [
                'qr_code_url' => is_string($qrUrl) ? $qrUrl : null,
                'transaction_reference' => is_string($txRef) || is_numeric($txRef)
                    ? (string) $txRef
                    : null,
                'merchant_reference' => is_string($merchantRef) && $merchantRef !== ''
                    ? $merchantRef
                    : null,
                'raw' => $json,
                'api_rejection' => false,
            ];
        }

        $apiRejection = array_key_exists('isSuccess', $json) && $json['isSuccess'] === false;

        Log::debug('RoutePay QR: candidate URL did not return success JSON.', [
            'request_url' => $requestUrl,
            'is_success' => $json['isSuccess'] ?? null,
            'error' => $json['error'] ?? null,
            'message' => $json['message'] ?? null,
            'response_code' => $json['responseCode'] ?? null,
            'validation_errors' => $json['validationErrors'] ?? null,
            'body_preview' => mb_substr($rawBody, 0, 400),
        ]);

        if ($apiRejection) {
            Log::warning('RoutePay QR: RoutePay declined QR generation (isSuccess=false).', [
                'request_url' => $requestUrl,
                'error' => $json['error'] ?? null,
                'message' => $json['message'] ?? null,
                'response_code' => $json['responseCode'] ?? null,
            ]);
        }

        return [
            'qr_code_url' => null,
            'transaction_reference' => null,
            'merchant_reference' => null,
            'raw' => $json,
            'api_rejection' => $apiRejection,
        ];
    }

    /**
     * Obtain OAuth2 access token (client_credentials).
     *
     * @param  array<string, mixed>|null  $routePaySettings  From Lga::routePaySettingsForQr()
     */
    public function requestAccessToken(?array $routePaySettings = null): ?string
    {
        $settings = $this->resolveRoutePaySettings($routePaySettings);
        $cacheKey = md5(json_encode([
            $settings['auth_url'] ?? '',
            $settings['client_id'] ?? '',
            $settings['client_secret'] ?? '',
        ]));

        if (array_key_exists($cacheKey, $this->accessTokenCache)) {
            return $this->accessTokenCache[$cacheKey];
        }

        $authUrl = $settings['auth_url'];
        $clientId = $settings['client_id'];
        $clientSecret = $settings['client_secret'];

        if (empty($authUrl) || empty($clientId) || empty($clientSecret)) {
            Log::warning('RoutePay QR: missing auth_url, client_id, or client_secret.');

            return null;
        }

        $response = Http::asForm()->timeout(30)->post($authUrl, [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        $this->logRoutePayOAuthHttpResponse((string) $authUrl, $response);

        if (! $response->successful()) {
            $clientId = (string) $clientId;
            Log::warning('RoutePay QR: token request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
                'auth_url' => $authUrl,
                'credential_source' => $routePaySettings['credential_source'] ?? ($routePaySettings !== null ? 'agency' : 'global_env'),
                'client_id_hint' => $clientId !== '' ? ('…'.substr($clientId, -4)) : '(empty)',
                'hint' => str_contains($response->body(), 'invalid_client')
                    ? 'OAuth invalid_client: client_id and client_secret must be a matching pair for this auth_url (check agency payment gateway fields vs ROUTEPAY_* in .env).'
                    : null,
            ]);

            return null;
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            Log::warning('RoutePay QR: token response missing access_token.');

            return $this->accessTokenCache[$cacheKey] = null;
        }

        return $this->accessTokenCache[$cacheKey] = $token;
    }

    /**
     * Amount as RoutePay curl examples show (e.g. "2500") — whole naira without ".00"; decimals when needed.
     */
    public static function amountFormStringForRoutePay(float $amount): string
    {
        $rounded = round($amount, 2);
        if (abs(fmod($rounded, 1.0)) < 0.00001) {
            return (string) (int) $rounded;
        }

        return number_format($rounded, 2, '.', '');
    }

    /**
     * Text fields for GenerateCustomizedQRDynamicPaymentLink — all string values, same names as RoutePay curl --form.
     * MerchantLogo is sent separately via Http::attach() (equivalent to curl --form 'MerchantLogo=@/path/file.png').
     *
     * @return array<string, string>
     */
    protected function buildRoutePayQrFormFields(
        string $dynamicName,
        float $amount,
        string $merchantReference,
        int $durationInSeconds,
        string $returnUrl,
        bool $verifyMe,
    ): array {
        return [
            'DynamicName' => $dynamicName,
            'Amount' => self::amountFormStringForRoutePay($amount),
            'DurationInSeconds' => (string) $durationInSeconds,
            'MerchantReference' => $merchantReference,
            'ReturnUrl' => trim($returnUrl),
            'VerifyMe' => $verifyMe ? 'true' : 'false',
        ];
    }

    /**
     * Filename for multipart file part — RoutePay validators often require a .png filename.
     */
    protected function routePayLogoUploadFilename(string $resolvedLogoPath): string
    {
        $base = basename($resolvedLogoPath);
        if ($base === '' || $base === '.' || $base === '..') {
            return 'merchant-logo.png';
        }
        if (preg_match('/\.png$/i', $base) !== 1) {
            $stem = pathinfo($base, PATHINFO_FILENAME);
            $stem = $stem !== '' ? $stem : 'merchant-logo';

            return $stem.'.png';
        }

        return $base;
    }

    /**
     * Resize PNG so longest edge is at most $maxEdge px and write a temp file (for RoutePay size limits).
     */
    protected function writeTempPngMaxEdge(string $sourcePath, int $maxEdge, int $pngCompression): ?string
    {
        if (! function_exists('imagecreatefrompng') || ! function_exists('imagepng')) {
            return null;
        }

        $src = @imagecreatefrompng($sourcePath);
        if ($src === false) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 1 || $h < 1) {
            imagedestroy($src);

            return null;
        }

        $maxEdge = max(32, $maxEdge);
        $scale = min($maxEdge / $w, $maxEdge / $h, 1.0);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        if ($dst === false) {
            imagedestroy($src);

            return null;
        }

        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
        imagealphablending($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);

        $tmp = tempnam(sys_get_temp_dir(), 'rp_mlogo_').'.png';
        $level = max(0, min(9, $pngCompression));
        if (! @imagepng($dst, $tmp, $level)) {
            imagedestroy($dst);
            @unlink($tmp);

            return null;
        }
        imagedestroy($dst);

        return $tmp;
    }

    /**
     * @return array{path: string, temp: string|null, original_bytes: int, upload_bytes: int}
     */
    protected function prepareMerchantLogoFileForUpload(string $resolvedPath, int $originalBytes): array
    {
        $maxBytes = (int) config('routepay.qr_merchant_logo_max_bytes', 120000);
        if ($maxBytes < 1) {
            return [
                'path' => $resolvedPath,
                'temp' => null,
                'original_bytes' => $originalBytes,
                'upload_bytes' => $originalBytes,
            ];
        }

        $maxBytes = max(10240, $maxBytes);
        if ($originalBytes <= $maxBytes) {
            return [
                'path' => $resolvedPath,
                'temp' => null,
                'original_bytes' => $originalBytes,
                'upload_bytes' => $originalBytes,
            ];
        }

        $edges = [512, 384, 256, 200, 160, 128, 96];
        foreach ($edges as $edge) {
            foreach ([8, 7, 6, 5] as $compression) {
                $tmp = $this->writeTempPngMaxEdge($resolvedPath, $edge, $compression);
                if ($tmp === null || ! is_file($tmp)) {
                    continue;
                }
                $ns = @filesize($tmp);
                if ($ns !== false && $ns > 0 && $ns <= $maxBytes) {
                    Log::info('RoutePay QR: merchant logo auto-downscaled for RoutePay upload limit.', [
                        'original_bytes' => $originalBytes,
                        'upload_bytes' => $ns,
                        'max_bytes' => $maxBytes,
                        'max_edge_px' => $edge,
                        'png_compression' => $compression,
                    ]);

                    return [
                        'path' => $tmp,
                        'temp' => $tmp,
                        'original_bytes' => $originalBytes,
                        'upload_bytes' => $ns,
                    ];
                }
                @unlink($tmp);
            }
        }

        Log::warning('RoutePay QR: logo exceeds max_bytes and could not be downscaled under limit; uploading original (may be rejected by RoutePay).', [
            'original_bytes' => $originalBytes,
            'max_bytes' => $maxBytes,
            'gd_available' => function_exists('imagecreatefrompng'),
        ]);

        return [
            'path' => $resolvedPath,
            'temp' => null,
            'original_bytes' => $originalBytes,
            'upload_bytes' => $originalBytes,
        ];
    }

    /**
     * POST GenerateCustomizedQRDynamicPaymentLink — multipart/form-data matching RoutePay curl:
     * text fields as strings + MerchantLogo file (Laravel Http::attach, same as --form MerchantLogo=@path).
     *
     * @return array{qr_code_url: string|null, transaction_reference: string|null, raw: array|null}
     */
    public function generateCustomizedQrDynamicPaymentLink(
        string $accessToken,
        string $dynamicName,
        float $amount,
        string $merchantReference,
        int $durationInSeconds,
        string $returnUrl,
        bool $verifyMe,
        string $merchantLogoAbsolutePath,
        ?array $routePaySettings = null,
    ): array {
        $settings = $this->resolveRoutePaySettings($routePaySettings);
        $baseUrl = $settings['base_url'];

        $resolvedLogoPath = $this->resolveMerchantLogoPath($merchantLogoAbsolutePath);
        if ($resolvedLogoPath === null) {
            Log::error('RoutePay QR: FAILED - merchant logo not readable.', [
                'configured' => $merchantLogoAbsolutePath,
                'error_type' => 'logo_not_found',
                'hint' => 'Use ROUTEPAY_MERCHANT_LOGO_PATH=assets/images/log.png or an absolute path to a PNG file.',
                'request_params' => [
                    'dynamicName' => $dynamicName,
                    'amount' => $amount,
                    'merchantReference' => $merchantReference,
                ],
            ]);

            return ['qr_code_url' => null, 'transaction_reference' => null, 'merchant_reference' => null, 'raw' => null];
        }

        $logoSize = @filesize($resolvedLogoPath);
        if (! is_readable($resolvedLogoPath) || $logoSize === false || $logoSize < 1) {
            Log::error('RoutePay QR: FAILED - could not read merchant logo file.', [
                'resolved' => $resolvedLogoPath,
                'error_type' => 'logo_read_failed',
                'file_exists' => file_exists($resolvedLogoPath),
                'is_readable' => is_readable($resolvedLogoPath),
                'file_size' => $logoSize,
                'request_params' => [
                    'dynamicName' => $dynamicName,
                    'amount' => $amount,
                    'merchantReference' => $merchantReference,
                ],
            ]);

            return ['qr_code_url' => null, 'transaction_reference' => null, 'merchant_reference' => null, 'raw' => null];
        }

        $merchantReference = self::normalizeMerchantReference($merchantReference);

        $form = $this->buildRoutePayQrFormFields(
            $dynamicName,
            (float) $amount,
            $merchantReference,
            $durationInSeconds,
            $returnUrl,
            $verifyMe,
        );

        $logoPrepared = $this->prepareMerchantLogoFileForUpload($resolvedLogoPath, (int) $logoSize);
        $logoPathForUpload = $logoPrepared['path'];
        $logoTempUnlink = $logoPrepared['temp'];
        $logoOriginalBytes = $logoPrepared['original_bytes'];
        $logoUploadBytes = $logoPrepared['upload_bytes'];
        $logoUploadFilename = $this->routePayLogoUploadFilename($resolvedLogoPath);
        $logoFieldName = trim((string) config('routepay.qr_merchant_logo_field', 'MerchantLogo'));
        if ($logoFieldName === '') {
            $logoFieldName = 'MerchantLogo';
        }

        $logoMaxBytes = max(0, (int) config('routepay.qr_merchant_logo_max_bytes', 120000));

        try {
            Log::debug('RoutePay QR: outbound request summary (multipart/form-data; same shape as curl --form)', [
                'routepay_form_fields' => $form,
                'note' => 'MerchantLogo is a separate multipart file part (curl: --form MerchantLogo=@/path/to.png), not a JSON object.',
                'merchant_logo_field' => $logoFieldName,
                'merchant_logo_filename' => $logoUploadFilename,
                'merchant_logo_source_path' => $resolvedLogoPath,
                'merchant_logo_upload_path' => $logoPathForUpload,
                'merchant_logo_original_bytes' => $logoOriginalBytes,
                'merchant_logo_upload_bytes' => $logoUploadBytes,
                'merchant_logo_max_bytes' => $logoMaxBytes > 0 ? $logoMaxBytes : null,
                'merchant_logo_delivery' => 'file_stream',
            ]);

            $candidates = $this->qrGenerateCandidateUrls(
                (string) $baseUrl,
                is_string($settings['qr_generate_override_url']) ? $settings['qr_generate_override_url'] : null,
                $settings['qr_alternate_base_urls'],
            );
            /** First explicit RoutePay isSuccess=false error (do not overwrite with redirect from a later candidate). */
            $routePayApiError = null;
            $lastTransportError = null;
            $candidateFailures = [];

            foreach ($candidates as $url) {
                $logoStream = fopen($logoPathForUpload, 'rb');
                if ($logoStream === false) {
                    Log::error('RoutePay QR: FAILED - could not open merchant logo for upload.', [
                        'upload_path' => $logoPathForUpload,
                        'request_url' => $url,
                    ]);
                    $candidateFailures[] = [
                        'url' => $url,
                        'error_type' => 'logo_fopen_failed',
                    ];

                    continue;
                }

                try {
                    $response = Http::withToken($accessToken)
                        ->withHeaders(['Accept' => 'application/json'])
                        ->withoutRedirecting()
                        ->timeout(60)
                        ->attach($logoFieldName, $logoStream, $logoUploadFilename, ['Content-Type' => 'image/png'])
                        ->post($url, $form);

                    $this->logRoutePayQrHttpResponse($url, $response);

                    if ($response->status() >= 300 && $response->status() < 400) {
                        if ($routePayApiError === null) {
                            Log::debug('RoutePay QR: HTTP redirect (not followed) — try another candidate.', [
                                'request_url' => $url,
                                'status' => $response->status(),
                                'location' => $response->header('Location'),
                            ]);
                            $lastTransportError = 'redirect';
                            $candidateFailures[] = [
                                'url' => $url,
                                'error_type' => 'http_redirect',
                                'status' => $response->status(),
                                'location' => $response->header('Location'),
                            ];
                        }

                        continue;
                    }

                    if (! $response->successful()) {
                        Log::error('RoutePay QR: candidate request failed with HTTP error.', [
                            'request_url' => $url,
                            'status' => $response->status(),
                            'body_preview' => mb_substr($response->body(), 0, 400),
                            'headers' => $response->headers(),
                        ]);
                        if ($routePayApiError === null) {
                            $lastTransportError = 'http_'.$response->status();
                            $candidateFailures[] = [
                                'url' => $url,
                                'error_type' => 'http_error',
                                'status' => $response->status(),
                                'body_preview' => mb_substr($response->body(), 0, 400),
                            ];
                        }

                        continue;
                    }

                    $rawBody = $response->body();
                    $parsed = $this->parseSuccessfulQrJsonResponse($rawBody, $url);
                    if ($parsed['qr_code_url'] !== null) {
                        Log::info('RoutePay QR: success.', ['request_url' => $url]);

                        return [
                            'qr_code_url' => $parsed['qr_code_url'],
                            'transaction_reference' => $parsed['transaction_reference'],
                            'merchant_reference' => $parsed['merchant_reference'],
                            'raw' => $parsed['raw'],
                        ];
                    }

                    if (! empty($parsed['api_rejection']) && is_array($parsed['raw'])) {
                        $msg = $parsed['raw']['error'] ?? $parsed['raw']['message'] ?? null;
                        if ($routePayApiError === null) {
                            $routePayApiError = (is_string($msg) && $msg !== '')
                                ? $msg
                                : 'RoutePay rejected QR (isSuccess=false, no error text)';
                            $candidateFailures[] = [
                                'url' => $url,
                                'error_type' => 'api_rejection',
                                'response' => $parsed['raw'],
                                'error_message' => $msg,
                            ];
                        }
                    } elseif ($routePayApiError === null) {
                        $lastTransportError = $lastTransportError ?? 'not_json_or_not_success';
                        $candidateFailures[] = [
                            'url' => $url,
                            'error_type' => 'invalid_json_or_not_success',
                            'body_preview' => mb_substr($rawBody, 0, 400),
                        ];
                    }
                } catch (\Exception $e) {
                    Log::error('RoutePay QR: exception during candidate request.', [
                        'request_url' => $url,
                        'exception_message' => $e->getMessage(),
                        'exception_class' => get_class($e),
                        'stack_trace' => $e->getTraceAsString(),
                    ]);
                    $candidateFailures[] = [
                        'url' => $url,
                        'error_type' => 'exception',
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                    ];
                } finally {
                    if (isset($logoStream) && is_resource($logoStream)) {
                        fclose($logoStream);
                    }
                }
            }

            $hints = [];
            if ($routePayApiError !== null) {
                $hints[] = 'RoutePay returned isSuccess=false; confirm with RoutePay: merchant QR product enabled, ReturnUrl allowlisted, OAuth client matches this API host, logo/size rules.';
            }
            if ($logoMaxBytes > 0 && $logoOriginalBytes > $logoMaxBytes && $logoTempUnlink === null) {
                $hints[] = 'Merchant logo file is large ('.$logoOriginalBytes.' bytes). Install/enable GD and keep ROUTEPAY_QR_MERCHANT_LOGO_MAX_BYTES so the app can downscale, or use a smaller PNG under public/assets/images/.';
            }
            $appEnv = (string) config('app.env', '');
            if (in_array($appEnv, ['production', 'staging'], true) && str_contains(strtolower((string) $baseUrl), 'apiqa')) {
                $hints[] = 'ROUTEPAY_BASE_URL points at apiqa (QA). For live traffic use production API base + ROUTEPAY_AUTH_URL from RoutePay (often https://api.routepay.com and production auth host).';
            }
            if ($routePayApiError === null && $lastTransportError === 'redirect') {
                $hints[] = 'Only transport failures seen (e.g. 301 to marketing site on legacy /api/v1 path). Set ROUTEPAY_QR_GENERATE_URL to the exact POST URL RoutePay provides.';
            }

            Log::error('RoutePay QR: FAILED - all endpoint candidates failed.', [
                'ROUTEPAY_BASE_URL' => $baseUrl,
                'attempted_urls' => $candidates,
                'total_candidates' => count($candidates),
                'route_pay_api_error' => $routePayApiError,
                'last_transport_error' => $lastTransportError,
                'candidate_failures' => $candidateFailures,
                'request_params' => [
                    'routepay_form_fields' => $form,
                    'merchant_logo_field' => $logoFieldName,
                    'merchant_logo_filename' => $logoUploadFilename,
                    'merchant_logo_original_bytes' => $logoOriginalBytes,
                    'merchant_logo_upload_bytes' => $logoUploadBytes,
                    'merchant_logo_max_bytes' => $logoMaxBytes > 0 ? $logoMaxBytes : null,
                    'merchant_logo_delivery' => 'file_stream',
                ],
                'hints' => $hints,
                'environment' => [
                    'app_env' => $appEnv,
                    'debug' => config('app.debug'),
                ],
            ]);

            return ['qr_code_url' => null, 'transaction_reference' => null, 'merchant_reference' => null, 'raw' => null];
        } finally {
            if ($logoTempUnlink !== null && is_file($logoTempUnlink)) {
                @unlink($logoTempUnlink);
            }
        }
    }

    /**
     * Full flow: token → generate QR link → HTTPS PNG URL for DomPDF (remote image, like receipt QuickChart).
     *
     * @return array{src: string|null, temp_path: string|null}
     */
    /**
     * @param  array{bill_id?: int|null, lga_id?: int|null, assessment_no?: string|null}|null  $billRegistration
     */
    public function buildBillQrImageForPdf(
        string $dynamicName,
        float $amountPayable,
        string $merchantReference,
        ?array $routePaySettings = null,
        ?array $billRegistration = null,
    ): array {
        if (! config('routepay.enabled')) {
            return ['src' => null, 'temp_path' => null];
        }

        if ($amountPayable <= 0) {
            return ['src' => null, 'temp_path' => null];
        }

        if (is_array($billRegistration) && ! empty($billRegistration['bill_id'])) {
            $existing = RoutePayQrRegistration::query()
                ->where('bill_id', $billRegistration['bill_id'])
                ->where(function ($query) {
                    $query->whereNotNull('qr_image_path')
                        ->orWhere(function ($inner) {
                            $inner->whereNotNull('qr_code_url')->where('qr_code_url', '!=', '');
                        });
                })
                ->first();

            if ($existing !== null && abs((float) $existing->amount_expected - $amountPayable) < 0.01) {
                return [
                    'src' => $existing->qr_image_path ?: $existing->qr_code_url,
                    'temp_path' => null,
                    'transaction_reference' => $existing->transaction_reference,
                    'merchant_reference' => $existing->merchant_reference,
                ];
            }
        }

        $settings = $this->resolveRoutePaySettings($routePaySettings);

        if ($routePaySettings !== null && empty($routePaySettings['configured'])) {
            Log::warning('RoutePay QR: agency payment gateway is incomplete.', [
                'credential_source' => 'agency',
                'auth_url_set' => ! empty($routePaySettings['auth_url']),
                'base_url_set' => ! empty($routePaySettings['base_url']),
                'client_id_set' => ! empty($routePaySettings['client_id']),
                'client_secret_set' => ! empty($routePaySettings['client_secret']),
                'hint' => 'Configure client ID, secret, auth URL, and API base URL on the agency record in LERA admin.',
            ]);

            return ['src' => null, 'temp_path' => null, 'transaction_reference' => null];
        }

        /** Shared LERA merchant logo for all agencies — not per-agency. */
        $logoPath = (string) config('routepay.merchant_logo_path');
        $returnUrl = trim((string) ($settings['qr_return_url'] ?? ''));
        if ($returnUrl === '') {
            $returnUrl = \App\Models\Lga::routePayCheckoutReturnUrl(null);
        }
        $duration = (int) $settings['qr_duration_seconds'];
        $verifyMe = (bool) $settings['qr_verify_me'];

        $token = $this->requestAccessToken($routePaySettings);
        if ($token === null) {
            Log::warning('RoutePay QR: aborted — OAuth token unavailable.', [
                'auth_url' => $settings['auth_url'] ?? null,
                'base_url' => $settings['base_url'] ?? null,
                'client_id_set' => ! empty($settings['client_id']),
                'agency_settings' => $routePaySettings !== null,
                'hint' => 'On production set ROUTEPAY_AUTH_URL + ROUTEPAY_BASE_URL (not authqa/apiqa). Run: php artisan routepay:diagnose-qr --lga=ID --try',
            ]);

            return ['src' => null, 'temp_path' => null, 'transaction_reference' => null];
        }

        $result = $this->generateCustomizedQrDynamicPaymentLink(
            $token,
            $dynamicName,
            $amountPayable,
            $merchantReference,
            $duration,
            $returnUrl,
            $verifyMe,
            (string) $logoPath,
            $routePaySettings,
        );

        $qrUrl = $result['qr_code_url'];
        if (! is_string($qrUrl) || $qrUrl === '') {
            return ['src' => null, 'temp_path' => null, 'transaction_reference' => null, 'merchant_reference' => null];
        }

        $routePayMerchantRef = $result['merchant_reference'] ?? null;
        if (! is_string($routePayMerchantRef) || $routePayMerchantRef === '') {
            $routePayMerchantRef = $merchantReference;
        }

        Log::info('RoutePay QR: registration refs for webhook lookup.', [
            'sent_merchant_reference' => $merchantReference,
            'routepay_merchant_reference' => $routePayMerchantRef,
            'transaction_reference' => $result['transaction_reference'] ?? null,
        ]);

        if (is_array($billRegistration)) {
            $this->persistBillQrRegistration(array_merge($billRegistration, [
                'merchant_reference' => $routePayMerchantRef,
                'transaction_reference' => $result['transaction_reference'] ?? null,
                'amount_expected' => $amountPayable,
                'qr_code_url' => $qrUrl,
            ]));
        }

        // RoutePay returns a stable HTTPS PNG URL — load it remotely in DomPDF (same pattern as receipt QuickChart URLs).
        return [
            'src' => $qrUrl,
            'temp_path' => null,
            'transaction_reference' => $result['transaction_reference'] ?? null,
            'merchant_reference' => $routePayMerchantRef,
        ];
    }

    /**
     * Persist bill ↔ RoutePay QR mapping for webhook settlement matching.
     *
     * @param  array{
     *   bill_id?: int|null,
     *   lga_id?: int|null,
     *   assessment_no?: string|null,
     *   merchant_reference: string,
     *   transaction_reference?: string|null,
     *   amount_expected?: float|int|string|null
     * }  $data
     */
    public function persistBillQrRegistration(array $data): ?RoutePayQrRegistration
    {
        $merchantReference = trim((string) ($data['merchant_reference'] ?? ''));
        if ($merchantReference === '') {
            return null;
        }

        $assessmentNo = trim((string) ($data['assessment_no'] ?? ''));
        $registrationData = [
            'bill_id' => $data['bill_id'] ?? null,
            'lga_id' => $data['lga_id'] ?? null,
            'assessment_no' => $assessmentNo !== '' ? $assessmentNo : null,
            'merchant_reference' => $merchantReference,
            'transaction_reference' => $data['transaction_reference'] ?? null,
            'qr_code_url' => $data['qr_code_url'] ?? null,
            'amount_expected' => (float) ($data['amount_expected'] ?? 0),
        ];

        if (! empty($registrationData['bill_id'])) {
            return RoutePayQrRegistration::updateOrCreate(
                ['bill_id' => $registrationData['bill_id']],
                $registrationData
            );
        }

        if ($registrationData['assessment_no'] !== null) {
            return RoutePayQrRegistration::updateOrCreate(
                ['assessment_no' => $registrationData['assessment_no']],
                $registrationData
            );
        }

        return RoutePayQrRegistration::updateOrCreate(
            ['merchant_reference' => $merchantReference],
            $registrationData
        );
    }

    /**
     * @param  array<string, mixed>|null  $routePaySettings
     * @return array{
     *   auth_url: string|null,
     *   client_id: string|null,
     *   client_secret: string|null,
     *   base_url: string,
     *   qr_generate_override_url: string|null,
     *   qr_return_url: string,
     *   qr_duration_seconds: int,
     *   qr_verify_me: bool,
     *   qr_alternate_base_urls: list<string>
     * }
     */
    /**
     * RoutePay hosted checkout (SetRequest) — redirects user to RoutePay payment page.
     *
     * @param  array{email: string, mobile: string, firstname: string, lastname: string, username?: string}  $customer
     * @param  list<array{name: string, unitPrice: string, quantity: int}>  $products
     * @return array{
     *   redirect_url: string|null,
     *   transaction_reference: string|null,
     *   merchant_reference: string,
     *   response_code: string|null,
     *   response_message: string|null,
     *   raw: array|null
     * }
     */
    public function initiateHostedPayment(
        float $amount,
        string $merchantReference,
        string $returnUrl,
        array $customer,
        array $products,
        ?array $routePaySettings = null,
    ): array {
        $empty = static fn (string $merchantReference): array => [
            'redirect_url' => null,
            'transaction_reference' => null,
            'merchant_reference' => $merchantReference,
            'response_code' => null,
            'response_message' => null,
            'raw' => null,
        ];

        $settings = $this->resolveRoutePaySettings($routePaySettings);
        $merchantReference = self::normalizeMerchantReference($merchantReference);

        if ($routePaySettings !== null && empty($routePaySettings['configured'])) {
            Log::warning('RoutePay hosted payment: agency payment gateway is incomplete.');

            return $empty($merchantReference);
        }

        $token = $this->requestAccessToken($routePaySettings);
        if ($token === null) {
            return array_merge($empty($merchantReference), [
                'response_message' => 'Could not obtain RoutePay access token.',
            ]);
        }

        $clientId = trim((string) ($settings['client_id'] ?? ''));
        $baseUrl = rtrim((string) ($settings['base_url'] ?? ''), '/');
        if ($clientId === '' || $baseUrl === '') {
            return array_merge($empty($merchantReference), [
                'response_message' => 'RoutePay client ID or API base URL is missing.',
            ]);
        }

        $requestUrl = $baseUrl.'/payment/api/v1/Payment/SetRequest';
        $payload = array_merge([
            'merchantId' => $clientId,
            'returnUrl' => trim($returnUrl),
            'merchantReference' => $merchantReference,
            'totalAmount' => self::amountFormStringForRoutePay($amount),
            'currency' => 'NGN',
            'paymentType' => 'PAYMENT',
            'customer' => [
                'email' => (string) ($customer['email'] ?? ''),
                'mobile' => (string) ($customer['mobile'] ?? ''),
                'firstname' => (string) ($customer['firstname'] ?? 'Customer'),
                'lastname' => (string) ($customer['lastname'] ?? ''),
                'username' => (string) ($customer['username'] ?? $customer['email'] ?? ''),
            ],
            'products' => array_values(array_map(static function (array $product): array {
                return [
                    'name' => (string) ($product['name'] ?? 'LERA Bill Payment'),
                    'unitPrice' => (string) ($product['unitPrice'] ?? '0'),
                    'quantity' => (int) ($product['quantity'] ?? 1),
                ];
            }, $products)),
        ]);

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(45)
            ->post($requestUrl, $payload);

        if ($this->shouldLogRoutePayHttpResponses()) {
            Log::info('RoutePay hosted payment: SetRequest response', [
                'request_url' => $requestUrl,
                'http_status' => $response->status(),
                'response_body' => $this->logResponseBodyForSupport($response->body()),
            ]);
        }

        if (! $response->successful()) {
            Log::warning('RoutePay hosted payment: SetRequest failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
                'request_url' => $requestUrl,
            ]);

            $decoded = $response->json();
            $message = is_array($decoded)
                ? (string) ($decoded['responseMessage'] ?? $decoded['message'] ?? 'RoutePay SetRequest failed')
                : 'RoutePay SetRequest failed';

            return array_merge($empty($merchantReference), [
                'response_message' => $message,
                'raw' => is_array($decoded) ? $decoded : null,
            ]);
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            return array_merge($empty($merchantReference), [
                'response_message' => 'RoutePay returned an invalid response.',
            ]);
        }

        $redirectUrl = $decoded['redirectUrl'] ?? $decoded['redirect_url'] ?? null;
        $transactionReference = $decoded['transactionReference'] ?? $decoded['transaction_reference'] ?? null;
        $storedMerchantReference = (string) ($decoded['merchantReference'] ?? $decoded['merchant_reference'] ?? $merchantReference);

        if (! is_string($redirectUrl) || $redirectUrl === '') {
            Log::warning('RoutePay hosted payment: SetRequest succeeded but redirectUrl missing.', [
                'response' => $decoded,
            ]);

            return [
                'redirect_url' => null,
                'transaction_reference' => is_string($transactionReference) ? $transactionReference : null,
                'merchant_reference' => self::normalizeMerchantReference($storedMerchantReference),
                'response_code' => isset($decoded['responseCode']) ? (string) $decoded['responseCode'] : null,
                'response_message' => (string) ($decoded['responseMessage'] ?? 'Missing redirect URL from RoutePay'),
                'raw' => $decoded,
            ];
        }

        return [
            'redirect_url' => $redirectUrl,
            'transaction_reference' => is_string($transactionReference) ? $transactionReference : null,
            'merchant_reference' => self::normalizeMerchantReference($storedMerchantReference),
            'response_code' => isset($decoded['responseCode']) ? (string) $decoded['responseCode'] : null,
            'response_message' => isset($decoded['responseMessage']) ? (string) $decoded['responseMessage'] : null,
            'raw' => $decoded,
        ];
    }

    /**
     * Query RoutePay for final payment status (when webhook was missed).
     *
     * @return array<string, mixed>|null
     */
    public function fetchPaymentTransaction(string $transactionReference, ?array $routePaySettings = null): ?array
    {
        $transactionReference = trim($transactionReference);
        if ($transactionReference === '') {
            return null;
        }

        $settings = $this->resolveRoutePaySettings($routePaySettings);
        $token = $this->requestAccessToken($routePaySettings);
        $baseUrl = rtrim((string) ($settings['base_url'] ?? ''), '/');

        if ($token === null || $baseUrl === '') {
            return null;
        }

        $urls = [
            $baseUrl.'/payment/api/v1/Payment/GetTransaction/'.rawurlencode($transactionReference),
            $baseUrl.'/payment/api/v1/Payment/get-transaction-record/'.rawurlencode($transactionReference),
        ];

        foreach ($urls as $url) {
            $response = Http::withToken($token)->acceptJson()->timeout(45)->get($url);
            if ($response->successful()) {
                $json = $response->json();
                if (is_array($json)) {
                    return $this->unwrapRoutePayTransactionResponse($json);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    protected function unwrapRoutePayTransactionResponse(array $json): array
    {
        if (isset($json['value']) && is_array($json['value'])) {
            return array_merge($json, $json['value']);
        }

        if (isset($json['data']) && is_array($json['data'])) {
            return array_merge($json, $json['data']);
        }

        return $json;
    }

    protected function resolveRoutePaySettings(?array $routePaySettings = null): array
    {
        if ($routePaySettings !== null) {
            return [
                'auth_url' => $routePaySettings['auth_url'] ?? null,
                'client_id' => $routePaySettings['client_id'] ?? null,
                'client_secret' => $routePaySettings['client_secret'] ?? null,
                'credential_source' => 'agency',
                'base_url' => rtrim((string) ($routePaySettings['base_url'] ?? ''), '/'),
                'qr_generate_override_url' => $routePaySettings['qr_generate_override_url'] ?? null,
                'qr_return_url' => (string) ($routePaySettings['qr_return_url'] ?? config('routepay.qr_return_url', config('app.url'))),
                'qr_duration_seconds' => (int) ($routePaySettings['qr_duration_seconds'] ?? config('routepay.qr_duration_seconds', 259200)),
                'qr_verify_me' => (bool) ($routePaySettings['qr_verify_me'] ?? config('routepay.qr_verify_me', true)),
                'qr_alternate_base_urls' => is_array($routePaySettings['qr_alternate_base_urls'] ?? null)
                    ? $routePaySettings['qr_alternate_base_urls']
                    : [],
            ];
        }

        $returnUrl = trim((string) config('routepay.qr_return_url', ''));
        if ($returnUrl === '') {
            $returnUrl = rtrim((string) config('app.url'), '/');
        }

        return [
            'auth_url' => config('routepay.auth_url'),
            'client_id' => config('routepay.client_id'),
            'client_secret' => config('routepay.client_secret'),
            'credential_source' => 'global_env',
            'base_url' => rtrim((string) config('routepay.base_url', 'https://apiqa.routepay.com'), '/'),
            'qr_generate_override_url' => config('routepay.qr_generate_override_url'),
            'qr_return_url' => $returnUrl,
            'qr_duration_seconds' => (int) config('routepay.qr_duration_seconds', 259200),
            'qr_verify_me' => (bool) config('routepay.qr_verify_me', true),
            'qr_alternate_base_urls' => config('routepay.qr_alternate_base_urls', []) ?: [],
        ];
    }
}
