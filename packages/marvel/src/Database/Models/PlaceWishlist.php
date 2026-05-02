<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceWishlist extends Model
{
    protected $table = 'place_wishlists';

    public $guarded = [];

    /**
     * Get the place that owns the wishlist.
     */
    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    /**
     * Get the user that owns the wishlist.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

