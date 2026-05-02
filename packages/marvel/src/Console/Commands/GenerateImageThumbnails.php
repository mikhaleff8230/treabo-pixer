<?php

namespace Marvel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateImageThumbnails extends Command
{
    protected $signature = 'marvel:images:generate-thumbnails {--force : Force regenerate all thumbnails}';
    protected $description = 'Generate thumbnails for all images in places';

    public function handle()
    {
        $this->info('Starting image thumbnail generation...');

        $images = DB::table('place_images')
            ->when(!$this->option('force'), function ($query) {
                return $query->whereNull('thumbnail_url');
            })
            ->get();

        if ($images->isEmpty()) {
            $this->info('No images found that need thumbnail generation.');
            return;
        }

        $this->info("Found {$images->count()} images to process.");

        $bar = $this->output->createProgressBar(count($images));
        $generated = 0;
        $errors = 0;

        foreach ($images as $image) {
            try {
                $success = $this->generateThumbnail($image);
                if ($success) {
                    $generated++;
                }
            } catch (\Exception $e) {
                $this->error("Error processing image {$image->id}: " . $e->getMessage());
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Generated thumbnails for {$generated} images");
        if ($errors > 0) {
            $this->warn("Encountered {$errors} errors");
        }
    }

    private function generateThumbnail($image)
    {
        try {
            $imagePath = storage_path('app/public/' . str_replace('/storage/', '', parse_url($image->url, PHP_URL_PATH)));
            
            if (!file_exists($imagePath)) {
                $this->warn("Image file not found: {$imagePath}");
                return false;
            }

            $imageId = $image->id;
            $thumbnailFileName = "places/images/thumbnails/{$imageId}.webp";
            $thumbnailPath = storage_path('app/public/' . $thumbnailFileName);
            
            // Создаем директорию для thumbnail
            $thumbnailDir = dirname($thumbnailPath);
            if (!is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }
            
            // Используем Intervention Image для создания thumbnail
            $imageInstance = \Intervention\Image\Facades\Image::make($imagePath);
            
            // Создаем thumbnail 400x400 с сохранением пропорций
            $imageInstance->fit(400, 400, function ($constraint) {
                $constraint->upsize();
            });
            
            // Сохраняем как WebP для лучшего сжатия
            $imageInstance->save($thumbnailPath, 85, 'webp');
            
            // Обновляем запись в базе
            DB::table('place_images')
                ->where('id', $image->id)
                ->update([
                    'thumbnail_url' => '/storage/' . $thumbnailFileName,
                    'updated_at' => now()
                ]);
            
            $this->info("Generated thumbnail for image {$image->id}");
            return true;
            
        } catch (\Exception $e) {
            $this->error("Error generating thumbnail for image {$image->id}: " . $e->getMessage());
            return false;
        }
    }
} 