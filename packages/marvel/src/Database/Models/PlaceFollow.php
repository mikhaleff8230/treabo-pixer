<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;

class PlaceFollow extends Model
{
    protected $fillable = [
        'seller_id',
        'user_id',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
} 