<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSubscription extends Model
{
    protected $table = 'subscriptions';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'product_id',
        'starts_at',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        if ($this->status === self::STATUS_CANCELLED || $this->status === self::STATUS_EXPIRED) {
            return false;
        }

        return $this->expires_at !== null && $this->expires_at->isFuture();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
