<?php

namespace Marvel\Console\Commands;

use Illuminate\Console\Command;
use Marvel\Database\Models\PlaceVideo;
use Illuminate\Support\Facades\Log;

class GenerateVideoPreviews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'marvel:videos:generate-previews {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate video previews and posters for existing videos';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting video preview generation...');

        $videos = PlaceVideo::whereNull('preview_url')
            ->orWhereNull('poster_url')
            ->get();

        if ($videos->isEmpty()) {
            $this->info('No videos found that need preview generation.');
            return 0;
        }

        $this->info("Found {$videos->count()} videos to process.");

        $bar = $this->output->createProgressBar($videos->count());
        $bar->start();

        $successCount = 0;
        $errorCount = 0;

        foreach ($videos as $video) {
            try {
                if ($this->generateVideoAssets($video)) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Error generating video assets', [
                    'video_id' => $video->id,
                    'error' => $e->getMessage()
                ]);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Processing complete!");
        $this->info("Success: {$successCount}");
        $this->info("Errors: {$errorCount}");

        return 0;
    }

    private function generateVideoAssets($videoRecord)
    {
        try {
            $videoPath = storage_path('app/public/' . str_replace('/storage/', '', $videoRecord->url));
            
            if (!file_exists($videoPath)) {
                Log::warning("Video file not found: {$videoPath}");
                return false;
            }

            // Проверяем, есть ли FFmpeg
            if (!class_exists('FFMpeg\FFMpeg')) {
                Log::warning('FFmpeg not available, skipping video processing');
                return false;
            }

            $ffmpeg = \FFMpeg\FFMpeg::create([
                'ffmpeg.binaries'  => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
                'ffprobe.binaries' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),
                'timeout'          => 60,
                'ffmpeg.threads'   => 4,
            ]);

            $videoFile = $ffmpeg->open($videoPath);
            
            // Получаем длительность видео
            $ffprobe = \FFMpeg\FFProbe::create([
                'ffprobe.binaries' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),
            ]);
            
            $duration = $ffprobe->format($videoPath)->get('duration');
            $videoId = $videoRecord->id;
            
            // 1. Создаем 3-секундное превью
            $previewFileName = "places/videos/preview/{$videoId}.mp4";
            $previewPath = storage_path('app/public/' . $previewFileName);
            
            // Создаем директорию для превью
            $previewDir = dirname($previewPath);
            if (!is_dir($previewDir)) {
                mkdir($previewDir, 0755, true);
            }
            
            // Обрезаем первые 3 секунды
            $previewDuration = min(3, $duration);
            $videoFile
                ->filters()
                ->clip(\FFMpeg\Coordinate\TimeCode::fromSeconds(0), \FFMpeg\Coordinate\TimeCode::fromSeconds($previewDuration));
            
            $videoFile->save(new \FFMpeg\Format\Video\X264(), $previewPath);
            
            // 2. Создаем постер (WebP) из первого кадра
            $posterFileName = "places/videos/poster/{$videoId}.webp";
            $posterPath = storage_path('app/public/' . $posterFileName);
            
            // Создаем директорию для постеров
            $posterDir = dirname($posterPath);
            if (!is_dir($posterDir)) {
                mkdir($posterDir, 0755, true);
            }
            
            // Извлекаем первый кадр и конвертируем в WebP
            $frame = $videoFile->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds(0));
            $frame->save($posterPath);
            
            // Обновляем запись в базе
            $videoRecord->update([
                'preview_url' => '/storage/' . $previewFileName,
                'poster_url' => '/storage/' . $posterFileName,
                'duration' => $duration
            ]);
            
            Log::info('Video assets generated successfully', [
                'video_id' => $videoRecord->id,
                'preview_path' => '/storage/' . $previewFileName,
                'poster_path' => '/storage/' . $posterFileName
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('FFmpeg error for video processing', [
                'video_id' => $videoRecord->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
} 