<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateMissingVideoThumbnails extends Command
{
    protected $signature = 'videos:generate-missing-thumbnails {--force : Force regenerate all thumbnails}';
    protected $description = 'Generate thumbnails for videos that don\'t have thumbnails';

    public function handle()
    {
        $this->info('Starting missing video thumbnail generation...');

        $videos = DB::table('place_videos')
            ->when(!$this->option('force'), function ($query) {
                return $query->whereNull('thumbnail_url');
            })
            ->get();

        if ($videos->isEmpty()) {
            $this->info('No videos found that need thumbnail generation.');
            return;
        }

        $this->info("Found {$videos->count()} videos to process.");

        $bar = $this->output->createProgressBar(count($videos));
        $generated = 0;
        $errors = 0;

        foreach ($videos as $video) {
            try {
                $thumbnailPath = $this->generateThumbnail($video);
                if ($thumbnailPath) {
                    DB::table('place_videos')
                        ->where('id', $video->id)
                        ->update([
                            'thumbnail_url' => $thumbnailPath,
                            'duration' => $this->getVideoDuration($video->url),
                            'updated_at' => now()
                        ]);
                    $generated++;
                }
            } catch (\Exception $e) {
                $this->error("Error processing video {$video->id}: " . $e->getMessage());
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Generated {$generated} thumbnails");
        if ($errors > 0) {
            $this->warn("Encountered {$errors} errors");
        }
    }

    private function generateThumbnail($video)
    {
        $videoPath = storage_path('app/public/' . str_replace('/storage/', '', parse_url($video->url, PHP_URL_PATH)));
        
        if (!file_exists($videoPath)) {
            $this->warn("Video file not found: {$videoPath}");
            return null;
        }

        try {
            // Проверяем, есть ли FFmpeg
            if (!class_exists('FFMpeg\FFMpeg')) {
                $this->warn('FFMpeg not available, skipping thumbnail generation');
                return null;
            }

            $ffmpeg = \FFMpeg\FFMpeg::create([
                'ffmpeg.binaries'  => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
                'ffprobe.binaries' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),
                'timeout'          => 30,
                'ffmpeg.threads'   => 4,
            ]);

            $videoFile = $ffmpeg->open($videoPath);
            
            // Получаем длительность видео
            $ffprobe = \FFMpeg\FFProbe::create([
                'ffprobe.binaries' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),
            ]);
            
            $duration = $ffprobe->format($videoPath)->get('duration');
            $thumbnailTime = min(1, $duration / 2); // Берем 1 секунду или середину для коротких видео
            
            $frame = $videoFile->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds($thumbnailTime));
            
            $thumbnailFileName = 'thumbnails/' . pathinfo($video->url, PATHINFO_FILENAME) . '_thumb.jpg';
            $thumbnailPath = storage_path('app/public/' . $thumbnailFileName);
            
            // Создаем директорию если не существует
            $thumbnailDir = dirname($thumbnailPath);
            if (!is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }
            
            $frame->save($thumbnailPath);
            
            return '/storage/' . $thumbnailFileName;
            
        } catch (\Exception $e) {
            $this->error("FFmpeg error for video {$video->id}: " . $e->getMessage());
            return null;
        }
    }

    private function getVideoDuration($videoUrl)
    {
        $videoPath = storage_path('app/public/' . str_replace('/storage/', '', parse_url($videoUrl, PHP_URL_PATH)));
        
        if (!file_exists($videoPath)) {
            return 0;
        }

        try {
            $ffprobe = \FFMpeg\FFProbe::create([
                'ffprobe.binaries' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),
            ]);
            
            return $ffprobe->format($videoPath)->get('duration');
        } catch (\Exception $e) {
            return 0;
        }
    }
} 