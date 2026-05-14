<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Optional payment QR when RoutePay image is unavailable
    |--------------------------------------------------------------------------
    |
    | If GenerateCustomizedQRDynamicPaymentLink does not return a QR (misconfigured
    | ROUTEPAY_*, etc.), you may set a public HTTPS URL template here. A QR will
    | encode that URL (QuickChart). Placeholders: {assessment_no}, {building_code},
    | {amount}, {year} (amount = total payable, 2 decimal places).
    |
    | Leave empty to show no QR rather than a useless internal/localhost link.
    |
    */
    'payment_qr_fallback_url' => env('BILL_PAYMENT_QR_FALLBACK_URL'),

];
