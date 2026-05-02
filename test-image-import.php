<?php

/**
 * Тестовый скрипт для проверки обработки изображений при импорте
 */

require_once __DIR__ . '/vendor/autoload.php';

// Загружаем Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ТЕСТ ОБРАБОТКИ ИЗОБРАЖЕНИЙ ===\n\n";

use Marvel\Services\XmlImportService;
use Marvel\Database\Models\Attachment;

// Тестовый URL изображения
$testImageUrl = 'https://via.placeholder.com/500x500.jpg';

echo "1. Тестируем загрузку изображения с URL:\n";
echo "   URL: {$testImageUrl}\n\n";

$service = new XmlImportService();

try {
    // Тестируем processImages (публичный метод)
    echo "2. Вызываем processImages...\n";
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('processImages');
    $method->setAccessible(true);
    $result = $method->invoke($service, $testImageUrl, ['download_images' => true]);
    
    // Если это массив, берем первый элемент
    if (is_array($result) && !empty($result)) {
        $result = $result[0];
    }
    
    echo "\n3. Результат:\n";
    if ($result) {
        echo "   ✅ Успешно создан attachment\n";
        echo "   Структура: " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        
        // Проверяем созданный attachment
        if (isset($result['id'])) {
            $attachment = Attachment::with('media')->find($result['id']);
            if ($attachment) {
                echo "\n4. Attachment из БД:\n";
                echo "   ID: {$attachment->id}\n";
                echo "   Медиа файлов: " . $attachment->media->count() . "\n";
                
                foreach ($attachment->media as $media) {
                    echo "\n   Media:\n";
                    echo "     - File: {$media->file_name}\n";
                    echo "     - Disk: {$media->disk}\n";
                    echo "     - URL: {$media->getUrl()}\n";
                    
                    if (strpos($media->mime_type, 'image/') !== false) {
                        try {
                            echo "     - Thumbnail: {$media->getUrl('thumbnail')}\n";
                        } catch (\Exception $e) {
                            echo "     - Thumbnail: ERROR - {$e->getMessage()}\n";
                        }
                    }
                }
                
                // Проверяем доступность URL
                echo "\n5. Проверка доступности:\n";
                $originalUrl = $result['original'];
                $ch = curl_init($originalUrl);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200) {
                    echo "   ✅ Original URL доступен (HTTP {$httpCode})\n";
                } else {
                    echo "   ❌ Original URL не доступен (HTTP {$httpCode})\n";
                }
                
                if (isset($result['thumbnail'])) {
                    $thumbnailUrl = $result['thumbnail'];
                    $ch = curl_init($thumbnailUrl);
                    curl_setopt($ch, CURLOPT_NOBODY, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode === 200) {
                        echo "   ✅ Thumbnail URL доступен (HTTP {$httpCode})\n";
                    } else {
                        echo "   ❌ Thumbnail URL не доступен (HTTP {$httpCode})\n";
                    }
                }
                
                // Удаляем тестовый attachment
                echo "\n6. Удаляем тестовый attachment...\n";
                $attachment->delete();
                echo "   ✅ Удалено\n";
            }
        }
    } else {
        echo "   ❌ Не удалось создать attachment\n";
    }
    
} catch (\Exception $e) {
    echo "   ❌ ОШИБКА: {$e->getMessage()}\n";
    echo "   Файл: {$e->getFile()}:{$e->getLine()}\n";
    echo "\nStack trace:\n{$e->getTraceAsString()}\n";
}

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";
