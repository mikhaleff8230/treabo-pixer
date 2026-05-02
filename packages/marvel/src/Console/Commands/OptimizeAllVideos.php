<?php

namespace Marvel\Console\Commands;

use Illuminate\Console\Command;
use Marvel\Database\Models\PlaceVideo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OptimizeAllVideos extends Command
{
    protected $signature = 'marvel:videos:optimize-all {--force} {--limit=50}';
    protected $description = 'Optimize all existing videos with previews, posters and thumbnails';

    public function handle()
    {
        $this->info('🎬 Starting mass video optimization...');
        
        // Проверяем FFmpeg
        if (!$this->checkFFmpeg()) {
            $this->error('❌ FFmpeg not found. Please install FFmpeg first.');
            return 1;
        }

        // Получаем видео для обработки
        $query = PlaceVideo::where(function($q) {
            $q->whereNull('preview_url')
              ->orWhereNull('poster_url')
              ->orWhereNull('duration');
        });

        if (!$this->option('force')) {
            $query->whereNull('preview_url');
        }

        $videos = $query->limit($this->option('limit'))->get();

        if ($videos->isEmpty()) {
            $this->info('✅ All videos are already optimized!');
            return 0;
        }

        $this->info("📊 Found {$videos->count()} videos to optimize");
        $this->info("💾 Estimated storage savings: ~70% for previews");

        // Создаем директории
        $this->createDirectories();

        // Обрабатываем видео
        $bar = $this->output->createProgressBar($videos->count());
        $bar->start();

        $stats = [
            'success' => 0,
            'error' => 0,
            'skipped' => 0,
            'total_size_saved' => 0
        ];

        foreach ($videos as $video) {
            try {
                $result = $this->optimizeVideo($video);
                
                if ($result['success']) {
                    $stats['success']++;
                    $stats['total_size_saved'] += $result['size_saved'] ?? 0;
                } else {
                    $stats['error']++;
                }
                
            } catch (\Exception $e) {
                $stats['error']++;
                Log::error('Video optimization error', [
                    'video_id' => $video->id,
                    'error' => $e->getMessage()
                ]);
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // Выводим статистику
        $this->displayStats($stats);

        return 0;
    }

    private function checkFFmpeg()
    {
        $ffmpegPath = env('FFMPEG_PATH', '/usr/bin/ffmpeg');
        return file_exists($ffmpegPath) && is_executable($ffmpegPath);
    }

    private function createDirectories()
    {
        $dirs = [
            'places/videos/preview',
            'places/videos/poster', 
            'places/videos/thumbnail'
        ];

        foreach ($dirs as $dir) {
            $path = storage_path("app/public/{$dir}");
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
                $this->line("📁 Created directory: {$dir}");
            }
        }
    }

    private function optimizeVideo($video)
    {
        try {
            $videoPath = storage_path('app/public/' . str_replace('/storage/', '', $video->url));
            
            if (!file_exists($videoPath)) {
                return ['success' => false, 'error' => 'Video file not found'];
            }

            $ffmpeg = \FFMpeg\FFMpeg::create([
                'ffmpeg.binaries'  => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
                'ffprobe.binaries' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),
                'timeout'          => 120,
                'ffmpeg.threads'   => 4,
            ]);

            $ffprobe = \FFMpeg\FFProbe::create([
                'ffprobe.binaries' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),
            ]);

            $videoFile = $ffmpeg->open($videoPath);
            
            // Получаем метаданные
            $duration = $ffprobe->format($videoPath)->get('duration');
            $streams = $ffprobe->streams($videoPath)->videos()->first();
            $width = $streams->get('width');
            $height = $streams->get('height');
            $fileSize = filesize($videoPath);
            $mimeType = mime_content_type($videoPath);

            $videoId = $video->id;
            $sizeSaved = 0;

            // 1. Создаем 3-секундное превью
            $previewFileName = "places/videos/preview/{$videoId}.mp4";
            $previewPath = storage_path('app/public/' . $previewFileName);
            
            $previewDuration = min(3, $duration);
            $videoFile
                ->filters()
                ->clip(\FFMpeg\Coordinate\TimeCode::fromSeconds(0), \FFMpeg\Coordinate\TimeCode::fromSeconds($previewDuration));
            
            $videoFile->save(new \FFMpeg\Format\Video\X264(), $previewPath);
            
            // 2. Создаем постер (WebP) из первого кадра
            $posterFileName = "places/videos/poster/{$videoId}.webp";
            $posterPath = storage_path('app/public/' . $posterFileName);
            
            $frame = $videoFile->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds(0));
            $frame->save($posterPath);
            
            // 3. Создаем thumbnail (JPEG) для списков
            $thumbnailFileName = "places/videos/thumbnail/{$videoId}.jpg";
            $thumbnailPath = storage_path('app/public/' . $thumbnailFileName);
            
            $frame = $videoFile->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds(min(1, $duration / 2)));
            $frame->save($thumbnailPath);
            
            // Вычисляем экономию места
            $previewSize = file_exists($previewPath) ? filesize($previewPath) : 0;
            $sizeSaved = $fileSize - $previewSize;
            
            // Обновляем запись в базе
            $video->update([
                'preview_url' => '/storage/' . $previewFileName,
                'poster_url' => '/storage/' . $posterFileName,
                'thumbnail_url' => '/storage/' . $thumbnailFileName,
                'duration' => $duration,
                'width' => $width,
                'height' => $height,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
            ]);
            
            return [
                'success' => true,
                'size_saved' => $sizeSaved,
                'preview_size' => $previewSize,
                'original_size' => $fileSize
            ];
            
        } catch (\Exception $e) {
            Log::error('Video optimization failed', [
                'video_id' => $video->id,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function displayStats($stats)
    {
        $this->newLine();
        $this->info('📈 Optimization Results:');
        $this->line("✅ Success: {$stats['success']}");
        $this->line("❌ Errors: {$stats['error']}");
        $this->line("⏭️  Skipped: {$stats['skipped']}");
        
        if ($stats['total_size_saved'] > 0) {
            $savedMB = round($stats['total_size_saved'] / 1024 / 1024, 2);
            $this->line("💾 Storage saved: {$savedMB} MB");
        }
        
        $this->newLine();
        $this->info('🎉 Video optimization completed!');
        $this->info('💡 Run this command again to process more videos.');
    }
} 