<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;

class PlaceSlugHistory extends Model
{
    protected $table = 'place_slug_history';

    protected $fillable = [
        'place_id',
        'old_slug',
        'language',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    /**
     * Найти Place по старому slug
     */
    public static function findPlaceByOldSlug(string $slug, string $language = 'ru'): ?Place
    {
        $history = self::where('old_slug', $slug)
            ->where('language', $language)
            ->with('place')
            ->first();

        return $history ? $history->place : null;
    }
}

