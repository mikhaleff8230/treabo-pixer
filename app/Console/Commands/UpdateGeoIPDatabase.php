<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateGeoIPDatabase extends Command
{
    protected $signature = 'geoip:update-database';
    protected $description = 'Update GeoIP database from GitHub';

    public function handle()
    {
        $this->info('Updating GeoIP database...');
        
        try {
            $dbPath = storage_path('app/geoip/GeoLite2-City.mmdb');
            
            // Скачиваем новую базу данных
            $this->info('Downloading database from GitHub...');
            $url = 'https://github.com/P3TERX/GeoLite.mmdb/releases/latest/download/GeoLite2-City.mmdb';
            $content = file_get_contents($url);
            
            if ($content === false) {
                throw new \Exception('Failed to download database');
            }
            
            // Сохраняем файл
            file_put_contents($dbPath, $content);
            
            // Проверяем размер файла
            if (file_exists($dbPath)) {
                $fileSize = filesize($dbPath);
                $this->info("Database updated successfully! Size: " . number_format($fileSize / 1024 / 1024, 2) . " MB");
            }
            
        } catch (\Exception $e) {
            $this->error('Failed to update GeoIP database: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
