<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;

class GenerateVideoThumbnails extends Command
{
    protected $signature = 'videos:generate-thumbnails {--force : Force regenerate existing thumbnails}';
    protected $description = 'Generate thumbnails for all videos in places';

    public function handle()
    {
        $this->info('Starting video thumbnail generation...');

        $videos = DB::table('place_videos')
            ->when(!$this->option('force'), function ($query) {
                return $query->whereNull('thumbnail_url');
            })
            ->get();

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
            $ffmpeg = FFMpeg::create([
                'ffmpeg.binaries'  => '/usr/bin/ffmpeg',
                'ffprobe.binaries' => '/usr/bin/ffprobe',
                'timeout'          => 3600,
                'ffmpeg.threads'   => 12,
            ]);

            $videoFile = $ffmpeg->open($videoPath);
            
            // Генерируем thumbnail на 1-й секунде (или в середине для коротких видео)
            $duration = $this->getVideoDuration($video->url);
            $thumbnailTime = min(1, $duration / 2);
            
            $frame = $videoFile->frame(TimeCode::fromSeconds($thumbnailTime));
            
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
                'ffprobe.binaries' => '/usr/bin/ffprobe',
            ]);
            
            return $ffprobe->format($videoPath)->get('duration');
        } catch (\Exception $e) {
            return 0;
        }
    }
} 