<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PlaceVideo extends Model
{
    protected $fillable = [
        'place_id',
        'url',
        'preview_url',
        'poster_url',
        'thumbnail_url',
        'duration',
        'width',
        'height',
        'file_size',
        'mime_type',
    ];

    protected $casts = [
        'duration' => 'decimal:2',
        'width' => 'integer',
        'height' => 'integer',
        'file_size' => 'integer',
    ];

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    /**
     * Получить URL для превью видео (3 секунды)
     */
    public function getPreviewUrlAttribute($value)
    {
        // Возвращаем null если превью еще не сгенерировано
        // Фронтенд не будет пытаться загрузить несуществующее превью
        return $value ? $this->buildFullUrl($value) : null;
    }

    /**
     * Получить URL для постера (первый кадр)
     */
    public function getPosterUrlAttribute($value)
    {
        return $value ? $this->buildFullUrl($value) : null;
    }

    /**
     * Получить URL для thumbnail
     */
    public function getThumbnailUrlAttribute($value)
    {
        return $value ? $this->buildFullUrl($value) : null;
    }

    /**
     * Получить URL для оригинального видео
     */
    public function getVideoUrlAttribute()
    {
        return $this->buildFullUrl($this->url);
    }

    /**
     * Проверить, есть ли оптимизированные версии
     */
    public function hasOptimizedVersions()
    {
        return !empty($this->preview_url) && !empty($this->poster_url);
    }

    /**
     * Получить размер файла в читаемом формате
     */
    public function getFormattedFileSizeAttribute()
    {
        if (!$this->file_size) return null;
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;
        
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }
        
        return round($size, 2) . ' ' . $units[$unit];
    }

    /**
     * Получить длительность в читаемом формате
     */
    public function getFormattedDurationAttribute()
    {
        if (!$this->duration) return null;
        
        $seconds = (int) $this->duration;
        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;
        
        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Построить полный URL
     */
    private function buildFullUrl($path)
    {
        if (empty($path)) return null;
        
        // Если уже полный URL
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $base = env('ASSETS_BASE_URL');
        
        // Отладка для первого видео
        if ($this->place_id == 1) {
            \Log::info('PlaceVideo::buildFullUrl - отладка', [
                'place_id' => $this->place_id,
                'input_path' => $path,
                'env_assets_base_url' => $base,
                'path_type' => gettype($path),
                'path_length' => is_string($path) ? strlen($path) : 'N/A'
            ]);
        }
        
        if ($base) {
            if (str_starts_with($path, '/storage/')) {
                $path = substr($path, strlen('/storage/'));
            }
            $fullUrl = rtrim($base, '/') . '/' . ltrim($path, '/');
            
            // Отладка для первого видео
            if ($this->place_id == 1) {
                \Log::info('PlaceVideo::buildFullUrl - результат', [
                    'place_id' => $this->place_id,
                    'input_path' => $path,
                    'base' => $base,
                    'full_url' => $fullUrl
                ]);
            }
            
            return $fullUrl;
        }

        // Попробуем построить URL через настроенное хранилище S3
        try {
            if (config('filesystems.disks.s3.bucket')) {
                return Storage::disk('s3')->url(ltrim($path, '/'));
            }
        } catch (\Throwable $exception) {
            // Игнорируем и используем старый fallback
        }

        // Fallback: старое поведение через домен API
        if (str_starts_with($path, '/storage/')) {
            return 'https://api.sancan.ru' . $path;
        }
        return 'https://api.sancan.ru/storage/' . ltrim($path, '/');
    }

    /**
     * Удалить файлы при удалении записи
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($video) {
            // Удаляем файлы с диска
            $files = [
                $video->url,
                $video->preview_url,
                $video->poster_url,
                $video->thumbnail_url,
            ];

            foreach ($files as $file) {
                if ($file && str_starts_with($file, '/storage/')) {
                    $path = str_replace('/storage/', '', $file);
                    if (Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }
            }
        });
    }
} 