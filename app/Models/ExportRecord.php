<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportRecord extends Model
{
    protected $fillable = [
        'user_id', 'type', 'file_path', 'status', 'message'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
