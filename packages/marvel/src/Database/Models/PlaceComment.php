<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlaceComment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'place_id',
        'user_id',
        'parent_id',
        'comment',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Отношение к плейсу
     */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /**
     * Отношение к пользователю
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Отношение к родительскому комментарию (для ответов)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(PlaceComment::class, 'parent_id');
    }

    /**
     * Отношение к дочерним комментариям (ответы)
     */
    public function replies()
    {
        return $this->hasMany(PlaceComment::class, 'parent_id')->orderBy('created_at', 'asc');
    }
}

