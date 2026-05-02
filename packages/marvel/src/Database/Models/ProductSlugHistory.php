<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSlugHistory extends Model
{
    protected $table = 'product_slug_history';

    protected $fillable = [
        'product_id',
        'old_slug',
        'language',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Найти Product по старому slug
     */
    public static function findProductByOldSlug(string $slug, string $language = 'ru'): ?Product
    {
        $history = self::where('old_slug', $slug)
            ->where('language', $language)
            ->with('product')
            ->first();

        return $history ? $history->product : null;
    }
}


