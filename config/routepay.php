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

    /** LERA merchant refs are 40 chars: LERA-{token13}-{YYYYMMDDHHmmss}-{seq6} */
    'merchant_reference_max_length' => (int) env('ROUTEPAY_MERCHANT_REFERENCE_MAX_LENGTH', 40),

    /** Comma-separated extra base URLs to try when the primary base returns HTML (e.g. https://paymentqa.routepay.com) */
    'qr_alternate_base_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('ROUTEPAY_QR_ALTERNATE_BASE_URLS', ''))))),

    /** Sandbox: authqa — production: use RoutePay production connect/token URL with production client credentials. */
    'auth_url' => env('ROUTEPAY_AUTH_URL', 'https://authqa.routepay.com/connect/token'),

    'client_id' => env('ROUTEPAY_CLIENT_ID'),

    'client_secret' => env('ROUTEPAY_CLIENT_SECRET') ?: env('ROUTEPAY_SECRET_KEY'),

    /** PNG file for MerchantLogo on all agencies' QR codes; absolute path or under public/ (e.g. assets/images/log.png). */
    'merchant_logo_path' => env('ROUTEPAY_MERCHANT_LOGO_PATH', public_path('assets/images/log.png')),

    /** Callback after payment; must be a valid URL per RoutePay. Falls back to APP_URL when empty. */
    'qr_return_url' => rtrim((string) env(
        'ROUTEPAY_QR_RETURN_URL',
        env('FRONTEND_URL', '')
    ), '/'),

    'qr_duration_seconds' => (int) env('ROUTEPAY_QR_DURATION_SECONDS', 259200),

    /** When true, sends VerifyMe=true (string) like RoutePay curl examples; false sends VerifyMe=false. */
    'qr_verify_me' => filter_var(env('ROUTEPAY_QR_VERIFY_ME', false), FILTER_VALIDATE_BOOLEAN),

    /**
     * Public webhook URL path (full URL = APP_URL + /api + this path).
     * Register the full URL in the RoutePay merchant portal.
     *
     * @see https://developer.routepay.com/?view=overview#webhook-url-configuration
     */
    'webhook_path' => '/routepay/webhook',

    /** Max age (seconds) for x-webhook-timestamp replay protection. */
    'webhook_timestamp_tolerance' => (int) env('ROUTEPAY_WEBHOOK_TIMESTAMP_TOLERANCE', 300),

    /**
     * Log each webhook request in Postman-ready form (laravel.log + storage/app/routepay-postman-last-request.json).
     * Enable while testing; disable in production when not needed.
     */
    'log_webhook_for_postman' => filter_var(
        env('ROUTEPAY_LOG_WEBHOOK_POSTMAN', env('APP_DEBUG', false)),
        FILTER_VALIDATE_BOOLEAN
    ),

    /** Multipart field name for the PNG logo (RoutePay default: MerchantLogo). */
    'qr_merchant_logo_field' => env('ROUTEPAY_QR_MERCHANT_LOGO_FIELD', 'MerchantLogo'),

    /**
     * Max MerchantLogo upload size (bytes). Larger files are downscaled with GD when possible (RoutePay often rejects huge PNGs).
     * Set ROUTEPAY_QR_MERCHANT_LOGO_MAX_BYTES=0 to skip downscaling.
     */
    'qr_merchant_logo_max_bytes' => (int) env('ROUTEPAY_QR_MERCHANT_LOGO_MAX_BYTES', 120000),

    /**
     * When true, logs full HTTP response bodies from RoutePay (OAuth + QR candidates) at INFO for
     * sharing with RoutePay support. OAuth responses redact access_token. Disable after debugging.
     */
    'log_http_responses' => filter_var(env('ROUTEPAY_LOG_HTTP_RESPONSES', false), FILTER_VALIDATE_BOOLEAN),

    /** Max characters of response body stored in logs (QR HTML/error pages can be large). */
    'log_response_body_max_chars' => (int) env('ROUTEPAY_LOG_RESPONSE_BODY_MAX_CHARS', 16384),

];
