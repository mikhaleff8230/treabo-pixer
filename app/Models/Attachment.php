<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    protected $fillable = [
        'message_id',
        'file_path',
        'file_type',
        'file_name',
        'file_size',
    ];

    /**
     * Get the message that owns the attachment.
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(\Marvel\Database\Models\Message::class, 'message_id');
    }
}









