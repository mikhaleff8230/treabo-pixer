<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'image',
        'details',
        'parent',
        'type_id'
    ];

    protected $casts = [
        'image' => 'array',
        'parent' => 'integer',
        'type_id' => 'integer'
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'category_product');
    }

    public function parentCategory()
    {
        return $this->belongsTo(Category::class, 'parent');
    }

    public function childCategories()
    {
        return $this->hasMany(Category::class, 'parent');
    }
}









