<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutePayQrRegistration extends Model
{
    protected $fillable = [
        'bill_id',
        'lga_id',
        'assessment_no',
        'merchant_reference',
        'transaction_reference',
        'qr_code_url',
        'qr_image_path',
        'amount_expected',
        'webhook_status',
    ];

    protected $casts = [
        'amount_expected' => 'float',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Billing::class, 'bill_id');
    }

    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class, 'lga_id');
    }
}
