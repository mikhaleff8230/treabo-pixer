<?php

namespace Marvel\Console;

use Illuminate\Console\Command;
use Marvel\Services\ChunkedImportService;
use Illuminate\Support\Facades\Log;

class CleanupImportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'imports:cleanup {--all : Clean all imports including completed} {--age=1 : Clean imports older than X hours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old or stuck import files and data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting import cleanup...');
        
        $cleanAll = $this->option('all');
        $ageHours = (int)$this->option('age');
        
        $progressDir = storage_path('app/xml-import-progress');
        $tempDir = storage_path('app/xml-import-temp');
        $statsDir = storage_path('app/xml-import-stats');
        $chunksDir = storage_path('app/xml-import-chunks');
        
        $cleaned = 0;
        $service = new ChunkedImportService();
        
        // 1. Очистка старых прогрессов
        if (is_dir($progressDir)) {
            $files = glob($progressDir . '/*.json');
            
            foreach ($files as $file) {
                $data = json_decode(file_get_contents($file), true);
                if (!$data) continue;
                
                $startedAt = isset($data['started_at']) ? strtotime($data['started_at']) : 0;
                $age = time() - $startedAt;
                $ageInHours = $age / 3600;
                
                $shouldClean = false;
                
                if ($cleanAll) {
                    $shouldClean = $ageInHours >= $ageHours;
                } else {
                    // Только незавершенные старше указанного времени
                    $shouldClean = $ageInHours >= $ageHours && 
                                   in_array($data['status'] ?? '', ['queued', 'processing', 'error']);
                }
                
                if ($shouldClean) {
                    $token = basename($file, '.json');
                    $service->cleanupImport($token);
                    $cleaned++;
                    
                    $this->line(sprintf(
                        'Cleaned: %s (age: %.1f hours, status: %s)',
                        $token,
                        $ageInHours,
                        $data['status'] ?? 'unknown'
                    ));
                }
            }
        }
        
        // 2. Очистка старых временных файлов
        if (is_dir($tempDir)) {
            $tempFiles = glob($tempDir . '/*');
            $tempCleaned = 0;
            
            foreach ($tempFiles as $file) {
                if (is_file($file)) {
                    $age = time() - filemtime($file);
                    $ageInHours = $age / 3600;
                    
                    if ($ageInHours >= $ageHours) {
                        @unlink($file);
                        $tempCleaned++;
                    }
                }
            }
            
            if ($tempCleaned > 0) {
                $this->line("Removed $tempCleaned old temp files");
            }
        }
        
        // 3. Создаем директории если их нет
        $dirs = [$progressDir, $tempDir, $statsDir, $chunksDir];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
                $this->line("Created directory: $dir");
            }
        }
        
        $this->info("Cleanup completed! Cleaned $cleaned imports.");
        
        Log::info('Manual import cleanup completed', [
            'cleaned' => $cleaned,
            'clean_all' => $cleanAll,
            'age_hours' => $ageHours
        ]);
        
        return 0;
    }
}

