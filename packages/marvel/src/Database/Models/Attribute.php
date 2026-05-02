<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Marvel\Exceptions\MarvelException;
use Marvel\Traits\TranslationTrait;

class Attribute extends Model
{
    use Sluggable, TranslationTrait;

    protected $table = 'attributes';

    protected $appends = ['translated_languages'];

    public $guarded = [];

    /**
     * Return the sluggable configuration array for this model.
     *
     * @return array
     */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name'
            ]
        ];
    }

    public function scopeWithUniqueSlugConstraints(Builder $query, Model $model): Builder
    {
        return $query->where('language', $model->language);
    }


    /**
     * @return HasMany
     */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class, 'attribute_id');
    }

    /**
     * @return BelongsToMany
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /**
     * @return BelongsToMany
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_attribute')
                    ->withPivot('is_required', 'sort_order')
                    ->withTimestamps();
    }

    /**
     * @return BelongsToMany
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_attribute_values')
                    ->withPivot('value', 'attribute_value_id')
                    ->withTimestamps();
    }

    /**
     * Get products with specific attribute value
     */
    public function productsWithValue($value)
    {
        return $this->products()->wherePivot('value', $value);
    }

    /**
     * Check if attribute is required for category
     */
    public function isRequiredForCategory($categoryId)
    {
        return $this->categories()->wherePivot('category_id', $categoryId)->wherePivot('is_required', true)->exists();
    }
}
