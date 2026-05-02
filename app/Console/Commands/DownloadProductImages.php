<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Attachment;
use Illuminate\Support\Facades\Log;

class DownloadProductImages extends Command
{
    protected $signature = 'products:download-images 
                            {--limit=100 : Количество товаров за раз}
                            {--shop_id= : ID магазина (опционально)}';

    protected $description = 'Загружает изображения товаров с внешних URL на сервер в фоновом режиме';

    public function handle()
    {
        $limit = (int)$this->option('limit');
        $shopId = $this->option('shop_id');
        
        $this->info('🚀 Начинаем фоновую загрузку изображений товаров...');
        
        // Ищем товары с внешними URL изображений (где id=null в image)
        $query = Product::whereNotNull('image')
            ->whereRaw("JSON_EXTRACT(image, '$.id') IS NULL OR JSON_EXTRACT(image, '$.id') = 'null'");
        
        if ($shopId) {
            $query->where('shop_id', $shopId);
        }
        
        $products = $query->limit($limit)->get();
        
        if ($products->isEmpty()) {
            $this->info('✅ Все изображения уже загружены!');
            return 0;
        }
        
        $this->info("Найдено товаров с внешними URL: {$products->count()}");
        
        $bar = $this->output->createProgressBar($products->count());
        $bar->start();
        
        $downloaded = 0;
        $errors = 0;
        
        foreach ($products as $product) {
            try {
                $updated = $this->downloadProductImage($product);
                if ($updated) {
                    $downloaded++;
                } else {
                    $errors++;
                }
            } catch (\Exception $e) {
                $errors++;
                Log::error('Failed to download image for product', [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'error' => $e->getMessage()
                ]);
            }
            
            $bar->advance();
            
            // Небольшая пауза чтобы не нагружать сервер
            usleep(100000); // 0.1 секунды
        }
        
        $bar->finish();
        $this->newLine(2);
        
        $this->info("✅ Загружено: {$downloaded}");
        $this->error("❌ Ошибок: {$errors}");
        
        return 0;
    }
    
    protected function downloadProductImage(Product $product): bool
    {
        $image = $product->image;
        
        if (!is_array($image) || empty($image['original'])) {
            return false;
        }
        
        $imageUrl = $image['original'];
        
        // Если уже attachment ID есть - пропускаем
        if (!empty($image['id']) && $image['id'] !== 'null') {
            return false;
        }
        
        // Проверяем что это URL
        if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        try {
            // Загружаем изображение
            $tempFile = $this->downloadToTemp($imageUrl);
            if (!$tempFile) {
                return false;
            }
            
            // Создаём Attachment
            $attachment = new Attachment();
            $attachment->save();
            
            $attachment->addMedia($tempFile)->toMediaCollection();
            
            // Удаляем временный файл
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
            
            // Обновляем товар с новым attachment
            foreach ($attachment->getMedia() as $media) {
                $product->image = [
                    'thumbnail' => $media->getUrl('thumbnail'),
                    'original' => $media->getUrl(),
                    'id' => $attachment->id
                ];
                $product->save();
                
                Log::info('Image downloaded for product', [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'attachment_id' => $attachment->id
                ]);
                
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('Failed to process image', [
                'url' => $imageUrl,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    protected function downloadToTemp(string $url): ?string
    {
        try {
            $tempFile = storage_path('app/temp/image_download_' . uniqid());
            
            if (!is_dir(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0777, true);
            }
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($imageData === false || $httpCode !== 200) {
                return null;
            }
            
            file_put_contents($tempFile, $imageData);
            
            return $tempFile;
            
        } catch (\Exception $e) {
            return null;
        }
    }
}

