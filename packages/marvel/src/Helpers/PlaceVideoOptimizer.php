<?php

namespace Marvel\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Marvel\Database\Models\PlaceVideo;

class PlaceVideoOptimizer
{
    /**
     * Оптимизировать видео плейса: создать превью, постер и thumbnail
     */
    public static function optimizeVideo(PlaceVideo $video, $videoPath = null)
    {
        try {
            // Если путь не передан, пытаемся получить из S3
            if (!$videoPath) {
                // Проверяем, есть ли файл в S3
                if (Storage::disk('s3')->exists($video->url)) {
                    // Скачиваем временно для обработки
                    $tempPath = storage_path('app/temp/' . uniqid('place_video_') . '_' . basename($video->url));
                    $dir = dirname($tempPath);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    file_put_contents($tempPath, Storage::disk('s3')->get($video->url));
                    $videoPath = $tempPath;
                } else {
                    Log::warning('PlaceVideoOptimizer: Video file not found in S3', ['url' => $video->url]);
                    return false;
                }
            }

            if (!file_exists($videoPath)) {
                Log::warning('PlaceVideoOptimizer: Video file not found', ['path' => $videoPath]);
                return false;
            }

            // Проверяем, есть ли FFmpeg
            if (!class_exists('FFMpeg\FFMpeg')) {
                Log::warning('PlaceVideoOptimizer: FFmpeg not available, skipping video processing');
                return false;
            }

            // Автоматически находим пути к FFmpeg и FFprobe
            $ffmpegPath = env('FFMPEG_PATH');
            $ffprobePath = env('FFPROBE_PATH');
            
            if (!$ffmpegPath) {
                $ffmpegPath = trim(shell_exec('which ffmpeg') ?: '/usr/bin/ffmpeg');
            }
            if (!$ffprobePath) {
                $ffprobePath = trim(shell_exec('which ffprobe') ?: '/usr/bin/ffprobe');
            }
            
            // Проверяем существование бинарников
            if (!file_exists($ffmpegPath) || !is_executable($ffmpegPath)) {
                Log::warning('PlaceVideoOptimizer: FFmpeg binary not found or not executable', ['path' => $ffmpegPath]);
                return false;
            }
            if (!file_exists($ffprobePath) || !is_executable($ffprobePath)) {
                Log::warning('PlaceVideoOptimizer: FFprobe binary not found or not executable', ['path' => $ffprobePath]);
                return false;
            }

            $ffmpeg = \FFMpeg\FFMpeg::create([
                'ffmpeg.binaries'  => $ffmpegPath,
                'ffprobe.binaries' => $ffprobePath,
                'timeout'          => 120,
                'ffmpeg.threads'   => 4,
            ]);

            $ffprobe = \FFMpeg\FFProbe::create([
                'ffprobe.binaries' => $ffprobePath,
            ]);

            $videoFile = $ffmpeg->open($videoPath);
            
            // Получаем метаданные
            $duration = $ffprobe->format($videoPath)->get('duration');
            $streams = $ffprobe->streams($videoPath)->videos()->first();
            $width = $streams ? $streams->get('width') : null;
            $height = $streams ? $streams->get('height') : null;
            $fileSize = filesize($videoPath);
            $mimeType = mime_content_type($videoPath);

            $videoId = $video->id;

            // 1. Создаем 3-секундное превью (оптимизированное)
            $previewFileName = "places/videos/preview/{$videoId}.mp4";
            $previewPath = storage_path('app/temp/' . uniqid('preview_') . '_' . basename($previewFileName));
            $previewDir = dirname($previewPath);
            if (!is_dir($previewDir)) {
                mkdir($previewDir, 0755, true);
            }
            
            $previewDuration = min(3, $duration);
            
            // Создаем новый экземпляр видео для превью (чтобы не изменять оригинал)
            $previewVideo = $ffmpeg->open($videoPath);
            $previewVideo
                ->filters()
                ->clip(\FFMpeg\Coordinate\TimeCode::fromSeconds(0), \FFMpeg\Coordinate\TimeCode::fromSeconds($previewDuration));
            
            // Используем более низкое качество для превью (меньший размер файла)
            $previewFormat = new \FFMpeg\Format\Video\X264();
            $previewFormat->setKiloBitrate(500); // Низкий битрейт для быстрой загрузки
            
            $previewVideo->save($previewFormat, $previewPath);
            
            // Открываем видео заново для извлечения кадров (после clip оригинальный объект изменен)
            $videoFile = $ffmpeg->open($videoPath);
            
            // Загружаем превью в S3
            if (file_exists($previewPath)) {
                Storage::disk('s3')->put($previewFileName, file_get_contents($previewPath), [
                    'visibility' => 'public',
                    'CacheControl' => 'public, max-age=31536000, immutable',
                    'ContentType' => 'video/mp4',
                ]);
                unlink($previewPath);
            }
            
            // 2. Создаем постер (WebP) из первого кадра
            $posterFileName = "places/videos/poster/{$videoId}.webp";
            $posterPath = storage_path('app/temp/' . uniqid('poster_') . '_' . basename($posterFileName));
            $posterDir = dirname($posterPath);
            if (!is_dir($posterDir)) {
                mkdir($posterDir, 0755, true);
            }
            
            $frame = $videoFile->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds(0));
            $frame->save($posterPath);
            
            // Загружаем постер в S3
            if (file_exists($posterPath)) {
                Storage::disk('s3')->put($posterFileName, file_get_contents($posterPath), [
                    'visibility' => 'public',
                    'CacheControl' => 'public, max-age=31536000, immutable',
                    'ContentType' => 'image/webp',
                ]);
                unlink($posterPath);
            }
            
            // 3. Создаем thumbnail (JPEG) для списков (сжатый вариант постера)
            $thumbnailFileName = "places/videos/thumbnail/{$videoId}.jpg";
            $thumbnailPath = storage_path('app/temp/' . uniqid('thumb_') . '_' . basename($thumbnailFileName));
            $thumbnailDir = dirname($thumbnailPath);
            if (!is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }
            
            // Берем кадр на 1 секунде или в середине для коротких видео
            $thumbnailTime = min(1, $duration / 2);
            $frame = $videoFile->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds($thumbnailTime));
            $frame->save($thumbnailPath);
            
            // Загружаем thumbnail в S3
            if (file_exists($thumbnailPath)) {
                Storage::disk('s3')->put($thumbnailFileName, file_get_contents($thumbnailPath), [
                    'visibility' => 'public',
                    'CacheControl' => 'public, max-age=31536000, immutable',
                    'ContentType' => 'image/jpeg',
                ]);
                unlink($thumbnailPath);
            }
            
            // Удаляем временный файл
            if ($videoPath && str_contains($videoPath, 'app/temp/')) {
                @unlink($videoPath);
            }
            
            // Обновляем запись в базе
            $video->update([
                'preview_url' => $previewFileName,
                'poster_url' => $posterFileName,
                'thumbnail_url' => $thumbnailFileName,
                'duration' => round($duration, 2),
                'width' => $width,
                'height' => $height,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
            ]);
            
            // Обновляем запись после сохранения, чтобы accessor'ы работали правильно
            $video->refresh();

            Log::info('PlaceVideoOptimizer: Video optimized successfully', [
                'video_id' => $video->id,
                'place_id' => $video->place_id,
                'preview_url' => $previewFileName,
                'poster_url' => $posterFileName,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('PlaceVideoOptimizer: Error optimizing video', [
                'video_id' => $video->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Удаляем временный файл при ошибке
            if (isset($videoPath) && $videoPath && str_contains($videoPath, 'app/temp/')) {
                @unlink($videoPath);
            }
            
            return false;
        }
    }
}

