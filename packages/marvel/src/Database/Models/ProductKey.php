<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductKey extends Model
{
    protected $fillable = [
        'product_id',
        'key',
        'used_by',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function usedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }
}
