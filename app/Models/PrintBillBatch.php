<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintBillBatch extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'batch_code',
        'user_id',
        'printed_by',
        'bill_count',
        'pdf_status',
        'pdf_path',
        'pdf_error',
        'pdf_generated_at',
    ];

    protected $casts = [
        'bill_count' => 'integer',
        'pdf_generated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isReady(): bool
    {
        return $this->pdf_status === self::STATUS_COMPLETED && ! empty($this->pdf_path);
    }
}
