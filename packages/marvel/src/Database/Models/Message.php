<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stevebauman\Purify\Casts\PurifyHtmlOnGet;

class Message extends Model
{

    public $timestamps = true;

    public $guarded = [];

    protected $fillable = [
        'conversation_id',
        'user_id',
        'body',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    // protected $appends = ['content'];

    // public function getContentAttribute() {
    //     return preg_replace('/\\\\(.?)/', "", $this->body);
    // }

    /**
     * @return belongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return belongsTo
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    /**
     * @return belongsTo
     */
    public function participant(): HasOne
    {
        return $this->hasOne(Participant::class, 'message_id');
    }

    /**
     * Get all chat attachments for the message.
     */
    public function chatAttachments(): HasMany
    {
        return $this->hasMany(ChatAttachment::class, 'message_id');
    }

    /**
     * Get all attachments for the message (alias for backward compatibility).
     */
    public function attachments(): HasMany
    {
        return $this->chatAttachments();
    }

}
