<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Marvel\Database\Factories\CommentFactory;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return CommentFactory::new();
    }

    protected $fillable = [
        'user_id',
        'commentable_type',
        'commentable_id',
        'parent_id',
        'body',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Отношение к пользователю
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Полиморфное отношение к комментируемой сущности (product, place, etc.)
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Отношение к родительскому комментарию (для ответов)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Отношение к дочерним комментариям (ответы)
     */
    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id')
            ->where('status', 'approved')
            ->orderBy('created_at', 'asc');
    }

    /**
     * Scope для получения только одобренных комментариев
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope для получения комментариев по типу и ID сущности
     */
    public function scopeForCommentable($query, $type, $id)
    {
        return $query->where('commentable_type', $type)
            ->where('commentable_id', $id);
    }

    /**
     * Scope для получения только родительских комментариев (без ответов)
     */
    public function scopeParentComments($query)
    {
        return $query->whereNull('parent_id');
    }
}

