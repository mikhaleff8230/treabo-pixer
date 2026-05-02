<?php
/**
 * Тестовый скрипт для проверки работы Яндекс геолокации
 * 
 * Использование:
 * php test-yandex-geo.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ТЕСТ ЯНДЕКС ГЕОЛОКАЦИИ ===\n\n";

$testIp = '95.24.18.3'; // Тестовый IP из России (Москва)

// Тест 1: Яндекс Locator
echo "1. Тест Яндекс Locator API\n";
echo "   IP: {$testIp}\n";
echo "   ---\n";

try {
    $yandexGeoService = app(\App\Services\YandexGeoService::class);
    $yandexApiKey = config('services.yandex_locator.api_key');
    
    if (empty($yandexApiKey)) {
        echo "   ❌ YANDEX_GEO_API_KEY не настроен в .env\n";
    } else {
        echo "   ✅ API ключ настроен: " . substr($yandexApiKey, 0, 10) . "...\n";
        
        $location = $yandexGeoService->getLocationByIp($testIp);
        
        if ($location) {
            echo "   ✅ Яндекс Locator работает!\n";
            echo "   Город: " . ($location['city'] ?? 'не определен') . "\n";
            echo "   Страна: " . ($location['country'] ?? 'не определена') . "\n";
            echo "   Координаты: " . ($location['lat'] ?? 0) . ", " . ($location['lon'] ?? 0) . "\n";
            echo "   Источник: " . ($location['source'] ?? 'unknown') . "\n";
        } else {
            echo "   ❌ Яндекс Locator вернул null\n";
        }
    }
} catch (\Exception $e) {
    echo "   ❌ Ошибка: " . $e->getMessage() . "\n";
    echo "   Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n";

// Тест 2: MaxMind
echo "2. Тест MaxMind GeoIP\n";
echo "   IP: {$testIp}\n";
echo "   ---\n";

try {
    $geoService = app(\App\Services\GeoLocationService::class);
    $dbPath = storage_path('app/geoip/GeoLite2-City.mmdb');
    
    if (!file_exists($dbPath)) {
        echo "   ❌ База данных MaxMind не найдена: {$dbPath}\n";
    } else {
        echo "   ✅ База данных найдена\n";
        
        $location = $geoService->getLocationByIp($testIp);
        
        if ($location && isset($location['city'])) {
            echo "   ✅ MaxMind работает!\n";
            echo "   Город: " . ($location['city'] ?? 'не определен') . "\n";
            echo "   Страна: " . ($location['country'] ?? 'не определена') . "\n";
            echo "   Источник: " . ($location['source'] ?? 'unknown') . "\n";
        } else {
            echo "   ❌ MaxMind вернул пустые данные\n";
        }
    }
} catch (\Exception $e) {
    echo "   ❌ Ошибка: " . $e->getMessage() . "\n";
    echo "   Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n";

// Тест 3: Yandex Geocoder
echo "3. Тест Yandex Geocoder API\n";
echo "   Запрос: Москва\n";
echo "   ---\n";

try {
    $geocoderApiKey = config('services.yandex_geocoder.api_key');
    
    if (empty($geocoderApiKey)) {
        echo "   ❌ YANDEX_GEOCODER_API_KEY не настроен в .env\n";
    } else {
        echo "   ✅ API ключ настроен: " . substr($geocoderApiKey, 0, 10) . "...\n";
        
        $response = \Illuminate\Support\Facades\Http::timeout(5)->get('https://geocode-maps.yandex.ru/1.x/', [
            'apikey' => $geocoderApiKey,
            'geocode' => 'Москва',
            'format' => 'json',
            'results' => 1,
            'kind' => 'locality'
        ]);
        
        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['response']['GeoObjectCollection']['featureMember'])) {
                $count = count($data['response']['GeoObjectCollection']['featureMember']);
                echo "   ✅ Yandex Geocoder работает!\n";
                echo "   Найдено результатов: {$count}\n";
            } else {
                echo "   ❌ Неверная структура ответа\n";
            }
        } else {
            echo "   ❌ HTTP ошибка: " . $response->status() . "\n";
            echo "   Ответ: " . $response->body() . "\n";
        }
    }
} catch (\Exception $e) {
    echo "   ❌ Ошибка: " . $e->getMessage() . "\n";
    echo "   Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n";

// Тест 4: Гибридная система
echo "4. Тест гибридной системы (MaxMind + Яндекс)\n";
echo "   IP: {$testIp}\n";
echo "   ---\n";

try {
    $geoService = app(\App\Services\GeoLocationService::class);
    $location = $geoService->getLocationByIp($testIp);
    
    if ($location) {
        echo "   ✅ Гибридная система работает!\n";
        echo "   Город: " . ($location['city'] ?? 'не определен') . "\n";
        echo "   Страна: " . ($location['country'] ?? 'не определена') . "\n";
        echo "   Источник: " . ($location['source'] ?? 'unknown') . "\n";
        if (isset($location['base_source'])) {
            echo "   Базовый источник: " . $location['base_source'] . "\n";
        }
    } else {
        echo "   ❌ Гибридная система вернула пустые данные\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Ошибка: " . $e->getMessage() . "\n";
    echo "   Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";

