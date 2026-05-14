<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RoutePayQrService
{
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
                'body_size' => mb_strlen($response->body()) . ' bytes',
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
                    'body_size' => mb_strlen($response->body()) . ' bytes',
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
                'body_size' => mb_strlen($response->body()) . ' bytes',
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
    protected function qrGenerateCandidateUrls(string $primaryBaseUrl): array
    {
        $override = config('routepay.qr_generate_override_url');
        if (is_string($override) && $override !== '') {
            return [$override];
        }

        $primaryBaseUrl = rtrim($primaryBaseUrl, '/');
        $paths = config('routepay.qr_endpoint_paths', [
            '/api/v1/QRCode/GenerateCustomizedQRDynamicPaymentLink',
        ]);

        if (! is_array($paths) || $paths === []) {
            $paths = ['/api/v1/QRCode/GenerateCustomizedQRDynamicPaymentLink'];
        }

        $bases = [$primaryBaseUrl];
        $extras = config('routepay.qr_alternate_base_urls', []);
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
     * Stable, unique reference for bills (≤ merchant_reference_max_length hex characters).
     */
    public static function merchantReferenceForBill(string $assessmentNo, mixed $year): string
    {
        $max = max(8, min(40, (int) config('routepay.merchant_reference_max_length', 40)));

        return substr(
            hash('sha256', $assessmentNo.'|'.(string) ($year ?? '').'|'.uniqid('', true).'|'.microtime(true)),
            0,
            $max
        );
    }

    /**
     * RoutePay validates MerchantReference length (sandbox: max 40). Longer refs return HTTP 400.
     */
    public static function normalizeMerchantReference(string $reference): string
    {
        $max = max(8, min(40, (int) config('routepay.merchant_reference_max_length', 40)));
        $reference = trim($reference);
        if ($reference === '') {
            return substr(hash('sha256', uniqid('', true).microtime(true)), 0, $max);
        }
        if (mb_strlen($reference) <= $max) {
            return $reference;
        }

        return substr(hash('sha256', $reference), 0, $max);
    }

    /**
     * @return array{qr_code_url: string|null, transaction_reference: string|null, raw: array|null, api_rejection: bool}
     */
    protected function parseSuccessfulQrJsonResponse(string $rawBody, string $requestUrl): array
    {
        $empty = static fn (): array => [
            'qr_code_url' => null,
            'transaction_reference' => null,
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
            $txRef = is_array($value) ? ($value['transactionReference'] ?? null) : null;

            return [
                'qr_code_url' => is_string($qrUrl) ? $qrUrl : null,
                'transaction_reference' => is_string($txRef) ? $txRef : null,
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
            'raw' => $json,
            'api_rejection' => $apiRejection,
        ];
    }

    /**
     * Obtain OAuth2 access token (client_credentials).
     */
    public function requestAccessToken(): ?string
    {
        $authUrl = config('routepay.auth_url');
        $clientId = config('routepay.client_id');
        $clientSecret = config('routepay.client_secret');

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
            Log::warning('RoutePay QR: token request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            Log::warning('RoutePay QR: token response missing access_token.');

            return null;
        }

        return $token;
    }

    /**
     * POST /api/v1/QRCode/GenerateCustomizedQRDynamicPaymentLink (multipart/form-data).
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
    ): array {
        $baseUrl = config('routepay.base_url');

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

            return ['qr_code_url' => null, 'transaction_reference' => null, 'raw' => null];
        }

        $logoContents = @file_get_contents($resolvedLogoPath);
        if ($logoContents === false || $logoContents === '') {
            Log::error('RoutePay QR: FAILED - could not read merchant logo file.', [
                'resolved' => $resolvedLogoPath,
                'error_type' => 'logo_read_failed',
                'file_exists' => file_exists($resolvedLogoPath),
                'is_readable' => is_readable($resolvedLogoPath),
                'file_size' => file_exists($resolvedLogoPath) ? filesize($resolvedLogoPath) : 'N/A',
                'request_params' => [
                    'dynamicName' => $dynamicName,
                    'amount' => $amount,
                    'merchantReference' => $merchantReference,
                ],
            ]);

            return ['qr_code_url' => null, 'transaction_reference' => null, 'raw' => null];
        }

        $merchantReference = self::normalizeMerchantReference($merchantReference);

        $form = [
            'DynamicName' => $dynamicName,
            'Amount' => (string) $amount,
            'DurationInSeconds' => (string) $durationInSeconds,
            'MerchantReference' => $merchantReference,
            'ReturnUrl' => $returnUrl,
            'VerifyMe' => $verifyMe ? 'true' : 'false',
        ];

        $candidates = $this->qrGenerateCandidateUrls((string) $baseUrl);
        /** First explicit RoutePay isSuccess=false error (do not overwrite with redirect from a later candidate). */
        $routePayApiError = null;
        $lastTransportError = null;
        $candidateFailures = [];

        foreach ($candidates as $url) {
            try {
                $response = Http::withToken($accessToken)
                    ->withoutRedirecting()
                    ->acceptJson()
                    ->timeout(60)
                    ->attach('MerchantLogo', $logoContents, basename($resolvedLogoPath), [
                        'Content-Type' => 'image/png',
                    ])
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
            }
        }

        $hints = [];
        if ($routePayApiError !== null) {
            $hints[] = 'RoutePay returned isSuccess=false; confirm with RoutePay: merchant QR product enabled, ReturnUrl allowlisted, OAuth client matches this API host, logo/size rules.';
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
                'dynamicName' => $dynamicName,
                'amount' => $amount,
                'merchantReference' => $merchantReference,
                'durationInSeconds' => $durationInSeconds,
                'returnUrl' => $returnUrl,
                'verifyMe' => $verifyMe,
            ],
            'hints' => $hints,
            'environment' => [
                'app_env' => $appEnv,
                'debug' => config('app.debug'),
            ],
        ]);

        return ['qr_code_url' => null, 'transaction_reference' => null, 'raw' => null];
    }

    /**
     * Full flow: token → generate QR link → HTTPS PNG URL for DomPDF (remote image, like receipt QuickChart).
     *
     * @return array{src: string|null, temp_path: string|null}
     */
    public function buildBillQrImageForPdf(
        string $dynamicName,
        float $amountPayable,
        string $merchantReference,
    ): array {
        if (! config('routepay.enabled')) {
            return ['src' => null, 'temp_path' => null];
        }

        if ($amountPayable <= 0) {
            return ['src' => null, 'temp_path' => null];
        }

        $logoPath = config('routepay.merchant_logo_path');
        $returnUrl = (string) config('routepay.qr_return_url');
        $duration = (int) config('routepay.qr_duration_seconds', 259200);
        $verifyMe = (bool) config('routepay.qr_verify_me', true);

        $token = $this->requestAccessToken();
        if ($token === null) {
            return ['src' => null, 'temp_path' => null];
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
        );

        $qrUrl = $result['qr_code_url'];
        if (! is_string($qrUrl) || $qrUrl === '') {
            return ['src' => null, 'temp_path' => null];
        }

        // RoutePay returns a stable HTTPS PNG URL — load it remotely in DomPDF (same pattern as receipt QuickChart URLs).
        return ['src' => $qrUrl, 'temp_path' => null];
    }
}
