<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'content_type',
        'content_url',
        'content_body',
        'position',
        'drip_days',
    ];

    protected $casts = [
        'drip_days' => 'integer',
        'position' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class, 'lesson_id');
    }
}
