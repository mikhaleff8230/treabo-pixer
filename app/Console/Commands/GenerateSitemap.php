<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Marvel\Http\Controllers\SitemapController;

class GenerateSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Генерирует sitemap.xml и все связанные файлы';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🚀 Начинаем генерацию sitemap...');
        
        try {
            $controller = new SitemapController();
            $response = $controller->index();
            
            $this->info('✅ Sitemap успешно сгенерирован!');
            $this->info('📁 Файлы сохранены в: ' . public_path('sitemaps'));
            $this->info('📄 Индекс сохранен в: ' . public_path('sitemap.xml'));
            
            // Проверяем наличие файлов
            $sitemapDir = public_path('sitemaps');
            if (is_dir($sitemapDir)) {
                $files = glob($sitemapDir . '/*.xml');
                $this->info('📊 Создано файлов sitemap: ' . count($files));
                
                foreach ($files as $file) {
                    $filename = basename($file);
                    $size = filesize($file);
                    $this->line("  - {$filename} (" . $this->formatBytes($size) . ")");
                }
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Ошибка при генерации sitemap: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }
    
    /**
     * Форматирует размер файла в читаемый вид
     */
    private function formatBytes($size, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
}

