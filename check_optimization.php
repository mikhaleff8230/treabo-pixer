<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Проверка оптимизации видео\n";
echo "==============================\n\n";

// Проверка БД
echo "�� База данных:\n";
$hasPreview = Schema::hasColumn('place_videos', 'preview_url');
$hasPoster = Schema::hasColumn('place_videos', 'poster_url');
$hasDuration = Schema::hasColumn('place_videos', 'duration');

echo "  - preview_url: " . ($hasPreview ? "✅" : "❌") . "\n";
echo "  - poster_url: " . ($hasPoster ? "✅" : "❌") . "\n";
echo "  - duration: " . ($hasDuration ? "✅" : "❌") . "\n\n";

// Проверка видео
echo "�� Видео:\n";
$totalVideos = \Marvel\Database\Models\PlaceVideo::count();
$optimizedVideos = \Marvel\Database\Models\PlaceVideo::whereNotNull('preview_url')->count();

echo "  - Всего видео: {$totalVideos}\n";
echo "  - Оптимизировано: {$optimizedVideos}\n";
echo "  - Процент: " . ($totalVideos > 0 ? round(($optimizedVideos / $totalVideos) * 100, 1) : 0) . "%\n\n";

// Проверка FFmpeg
echo "🎥 FFmpeg:\n";
$ffmpegExists = file_exists('/usr/bin/ffmpeg');
$ffmpegExecutable = is_executable('/usr/bin/ffmpeg');
$phpFFmpeg = class_exists('FFMpeg\FFMpeg');

echo "  - FFmpeg установлен: " . ($ffmpegExists ? "✅" : "❌") . "\n";
echo "  - FFmpeg исполняемый: " . ($ffmpegExecutable ? "✅" : "❌") . "\n";
echo "  - PHP-FFmpeg: " . ($phpFFmpeg ? "✅" : "❌") . "\n\n";

// Проверка папок
echo "�� Файловая система:\n";
$previewDir = storage_path('app/public/places/videos/preview');
$posterDir = storage_path('app/public/places/videos/poster');
$thumbnailDir = storage_path('app/public/places/videos/thumbnail');

echo "  - Папка preview: " . (is_dir($previewDir) ? "✅" : "❌") . "\n";
echo "  - Папка poster: " . (is_dir($posterDir) ? "✅" : "❌") . "\n";
echo "  - Папка thumbnail: " . (is_dir($thumbnailDir) ? "✅" : "❌") . "\n\n";

echo "🎯 Статус: " . ($hasPreview && $hasPoster && $hasDuration && $ffmpegExists && $ffmpegExecutable && $phpFFmpeg ? "✅ ГОТОВ К РАБОТЕ" : "❌ ТРЕБУЕТ НАСТРОЙКИ") . "\n";
