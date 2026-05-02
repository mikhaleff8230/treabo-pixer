<?php

require_once 'vendor/autoload.php';

// Инициализация Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Marvel\Database\Models\Place;
use Marvel\Database\Models\PlaceVideo;

echo "=== Диагностика видео в плейсах ===\n\n";

// Проверяем все плейсы
$places = Place::with(['videos', 'images'])->get();

echo "Всего плейсов: " . $places->count() . "\n\n";

foreach ($places as $place) {
    echo "Place ID: {$place->id} | Title: {$place->title}\n";
    echo "Изображений: " . $place->images->count() . "\n";
    echo "Видео: " . $place->videos->count() . "\n";
    
    if ($place->videos->count() > 0) {
        foreach ($place->videos as $video) {
            echo "  - Video ID: {$video->id} | URL: {$video->url}\n";
            $fullPath = public_path($video->url);
            echo "  - Full path: {$fullPath}\n";
            echo "  - File exists: " . (file_exists($fullPath) ? 'YES' : 'NO') . "\n";
            if (file_exists($fullPath)) {
                echo "  - File size: " . filesize($fullPath) . " bytes\n";
            }
        }
    }
    echo "---\n";
}

// Проверяем таблицу place_videos напрямую
echo "\n=== Прямая проверка таблицы place_videos ===\n";
$videos = PlaceVideo::all();
echo "Всего записей в place_videos: " . $videos->count() . "\n";

foreach ($videos as $video) {
    echo "ID: {$video->id} | Place ID: {$video->place_id} | URL: {$video->url}\n";
}

echo "\n=== Диагностика завершена ===\n"; 