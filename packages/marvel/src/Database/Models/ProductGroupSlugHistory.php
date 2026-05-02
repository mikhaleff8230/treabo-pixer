<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;

class ProductGroupSlugHistory extends Model
{
    protected $table = 'product_group_slug_history';

    protected $fillable = [
        'product_group_id',
        'old_slug',
        'language',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function productGroup()
    {
        return $this->belongsTo(ProductGroup::class);
    }

    /**
     * Найти ProductGroup по старому slug
     */
    public static function findGroupByOldSlug(string $slug, string $language = 'ru'): ?ProductGroup
    {
        $history = self::where('old_slug', $slug)
            ->where('language', $language)
            ->with('productGroup')
            ->first();

        return $history ? $history->productGroup : null;
    }
}


