<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'title',
        'description',
        'required_product_id',
    ];

    public function requiredProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'required_product_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position')->orderBy('id');
    }
}
