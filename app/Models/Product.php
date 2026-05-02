<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type_id',
        'price',
        'shop_id',
        'sale_price',
        'min_price',
        'max_price',
        'sku',
        'preview_url',
        'quantity',
        'in_stock',
        'is_taxable',
        'shipping_class_id',
        'status',
        'product_type',
        'unit',
        'height',
        'width',
        'length',
        'weight',
        'image',
        'video',
        'gallery',
        'author_id',
        'manufacturer_id',
        'is_digital',
        'is_external',
        'external_product_url',
        'external_product_button_text'
    ];

    protected $casts = [
        'image' => 'array',
        'gallery' => 'array',
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'quantity' => 'integer',
        'in_stock' => 'boolean',
        'is_taxable' => 'boolean',
        'is_digital' => 'boolean',
        'is_external' => 'boolean',
        'height' => 'decimal:2',
        'width' => 'decimal:2',
        'length' => 'decimal:2',
        'weight' => 'decimal:2',
    ];

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'attribute_product');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'product_tag');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function author()
    {
        return $this->belongsTo(Author::class);
    }

    public function manufacturer()
    {
        return $this->belongsTo(Manufacturer::class);
    }
}









