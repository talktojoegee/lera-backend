<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bill payment QR (encoded on PDF bills)
    |--------------------------------------------------------------------------
    |
    | QR codes point to the public payment page (Odoo), not RoutePay dynamic QR.
    | Placeholders: {assessment_no}, {building_code}, {amount}, {year}
    |
    | Example: https://lera.odoo.com/payment?assessment={assessment_no}
    |
    */
    'payment_qr_url' => env(
        'BILL_PAYMENT_QR_URL',
        'https://lera.odoo.com/payment?assessment={assessment_no}'
    ),

    'payment_qr_size' => (int) env('BILL_PAYMENT_QR_SIZE', 180),

    /** @deprecated Use payment_qr_url. Kept for backward-compatible env keys. */
    'payment_qr_fallback_url' => env(
        'BILL_PAYMENT_QR_FALLBACK_URL',
        env('BILL_PAYMENT_QR_URL', 'https://lera.odoo.com/payment?assessment={assessment_no}')
    ),

    /*
    |--------------------------------------------------------------------------
    | Batch bill PDF generation (print-by-batch)
    |--------------------------------------------------------------------------
    */
    'batch_pdf_max_execution_seconds' => (int) env('BILL_BATCH_PDF_MAX_EXECUTION_SECONDS', 0),
    'batch_pdf_memory_limit' => env('BILL_BATCH_PDF_MEMORY_LIMIT', '1024M'),
    /** Render N bills per DomPDF pass, then merge (0 = single pass). */
    'batch_pdf_chunk_size' => (int) env('BILL_BATCH_PDF_CHUNK_SIZE', 50),
    'batch_pdf_disk' => env('BILL_BATCH_PDF_DISK', 'local'),
    'batch_pdf_queue' => env('BILL_BATCH_PDF_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Bill Excel export (background job)
    |--------------------------------------------------------------------------
    */
    'export_queue' => env('BILL_EXPORT_QUEUE', 'default'),
    'export_disk' => env('BILL_EXPORT_DISK', 'public'),
    'export_memory_limit' => env('BILL_EXPORT_MEMORY_LIMIT', '2G'),

];
