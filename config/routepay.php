<?php

return [

    /*
    |--------------------------------------------------------------------------
    | RoutePay QR (GenerateCustomizedQRDynamicPaymentLink)
    |--------------------------------------------------------------------------
    | See RoutePay API guide: OAuth client_credentials, then multipart POST to
    | /api/v1/QRCode/GenerateCustomizedQRDynamicPaymentLink
    |
    | IMPORTANT: base_url must be the API host (apiqa / api), NOT https://routepay.com
    | (that URL returns HTML and QR calls will fail).
    */

    'enabled' => env('ROUTEPAY_QR_ENABLED', true),

    /** Sandbox: https://apiqa.routepay.com — Production: https://api.routepay.com (set both in .env for live; do not leave apiqa on production APP_ENV). */
    'base_url' => rtrim(env('ROUTEPAY_BASE_URL', 'https://apiqa.routepay.com'), '/'),

    /**
     * If RoutePay support gave you an exact POST URL, set it here (skips path/base guessing).
     * Example: https://paymentqa.routepay.com/payment/api/v1/QRCode/GenerateCustomizedQRDynamicPaymentLink
     */
    'qr_generate_override_url' => (($qrUrl = trim((string) env('ROUTEPAY_QR_GENERATE_URL', ''))) !== '')
        ? rtrim($qrUrl, '/')
        : null,

    /**
     * Path suffixes tried (in order) on each base host. Sandbox apiqa redirects /api/v1/* —
     * /payment/api/v1/QRCode/* is the working route.
     */
    'qr_endpoint_paths' => [
        '/payment/api/v1/QRCode/GenerateCustomizedQRDynamicPaymentLink',
        '/api/v1/QRCode/GenerateCustomizedQRDynamicPaymentLink',
    ],

    /** RoutePay rejects longer MerchantReference strings (validation). */
    'merchant_reference_max_length' => (int) env('ROUTEPAY_MERCHANT_REFERENCE_MAX_LENGTH', 40),

    /** Comma-separated extra base URLs to try when the primary base returns HTML (e.g. https://paymentqa.routepay.com) */
    'qr_alternate_base_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('ROUTEPAY_QR_ALTERNATE_BASE_URLS', ''))))),

    /** Sandbox: authqa — production: use RoutePay production connect/token URL with production client credentials. */
    'auth_url' => env('ROUTEPAY_AUTH_URL', 'https://authqa.routepay.com/connect/token'),

    'client_id' => env('ROUTEPAY_CLIENT_ID'),

    'client_secret' => env('ROUTEPAY_CLIENT_SECRET') ?: env('ROUTEPAY_SECRET_KEY'),

    /** PNG file for MerchantLogo; absolute path or under public/ (e.g. assets/images/log.png). */
    'merchant_logo_path' => env('ROUTEPAY_MERCHANT_LOGO_PATH', public_path('assets/images/log.png')),

    /** Callback after payment; must be a valid URL per RoutePay. */
    'qr_return_url' => env('ROUTEPAY_QR_RETURN_URL', env('APP_URL', 'http://localhost')),

    'qr_duration_seconds' => (int) env('ROUTEPAY_QR_DURATION_SECONDS', 259200),

    'qr_verify_me' => filter_var(env('ROUTEPAY_QR_VERIFY_ME', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * When true, logs full HTTP response bodies from RoutePay (OAuth + QR candidates) at INFO for
     * sharing with RoutePay support. OAuth responses redact access_token. Disable after debugging.
     */
    'log_http_responses' => filter_var(env('ROUTEPAY_LOG_HTTP_RESPONSES', false), FILTER_VALIDATE_BOOLEAN),

    /** Max characters of response body stored in logs (QR HTML/error pages can be large). */
    'log_response_body_max_chars' => (int) env('ROUTEPAY_LOG_RESPONSE_BODY_MAX_CHARS', 16384),

];
