<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatAttachment extends Model
{
    protected $table = 'chat_attachments';

    protected $fillable = [
        'message_id',
        'file_path',
        'file_type',
        'file_name',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    /**
     * Get the message that owns the attachment.
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }
}








