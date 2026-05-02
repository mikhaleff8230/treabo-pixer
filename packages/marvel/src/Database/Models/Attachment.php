<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;


class Attachment extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'attachments';

    public $guarded = [];

    public function registerMediaConversions(Media $media = null): void
    {
        // Thumbnail для списков товаров - оптимизированный размер для карточек
        // 600x600px обеспечивает хорошее качество на retina дисплеях
        // и достаточно для карточек в гриде (обычно 300-400px)
        $this->addMediaConversion('thumbnail')
            ->width(600)
            ->height(600)
            ->quality(90) // Высокое качество для thumbnail
            ->format('webp') // WebP для лучшего сжатия
            ->nonQueued();
        
        // Дополнительный размер для средних изображений (если нужно)
        $this->addMediaConversion('medium')
            ->width(1200)
            ->height(1200)
            ->quality(85)
            ->format('webp')
            ->nonQueued();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('default');
    }
}
