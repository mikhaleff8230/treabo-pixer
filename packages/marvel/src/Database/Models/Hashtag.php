<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Hashtag extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($hashtag) {
            if (empty($hashtag->slug)) {
                $hashtag->slug = Str::slug($hashtag->name);
            }
        });

        static::updating(function ($hashtag) {
            if ($hashtag->isDirty('name') && empty($hashtag->slug)) {
                $hashtag->slug = Str::slug($hashtag->name);
            }
        });
    }

    public function places()
    {
        return $this->belongsToMany(Place::class, 'place_hashtag');
    }
} 