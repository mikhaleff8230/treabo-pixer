<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSkuSlugHistory extends Model
{
    protected $table = 'product_sku_slug_history';

    protected $fillable = [
        'product_sku_id',
        'old_slug',
        'language',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function productSku()
    {
        return $this->belongsTo(ProductSku::class);
    }

    /**
     * Найти ProductSku по старому slug
     */
    public static function findSkuByOldSlug(string $slug, string $language = 'ru'): ?ProductSku
    {
        $history = self::where('old_slug', $slug)
            ->where('language', $language)
            ->with('productSku')
            ->first();

        return $history ? $history->productSku : null;
    }
}


