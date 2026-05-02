<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;

class PlaceLike extends Model
{
    protected $fillable = [
        'place_id',
        'user_id',
        'ip_address',
        'anonymous_id',
    ];

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
} 