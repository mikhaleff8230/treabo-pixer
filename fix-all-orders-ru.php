<?php
/**
 * Обновление ВСЕХ заказов с language='en' на 'ru'
 */

// Проверяем где мы находимся
$root = __DIR__;
$vendorPath = $root . '/vendor/autoload.php';
$bootstrapPath = $root . '/bootstrap/app.php';

// Если скрипт запущен из корня проекта
if (!file_exists($vendorPath)) {
    $vendorPath = $root . '/pixer-api/vendor/autoload.php';
    $bootstrapPath = $root . '/pixer-api/bootstrap/app.php';
}

require $vendorPath;
$app = require_once $bootstrapPath;
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "=== Обновление ВСЕХ заказов ===\n\n";
    
    // Получаем все заказы с language='en'
    $ordersEn = DB::table('orders')->where('language', 'en')->count();
    echo "Найдено заказов с language='en': $ordersEn\n\n";
    
    if ($ordersEn > 0) {
        // Обновляем ВСЕ
        $updated = DB::table('orders')
            ->where('language', 'en')
            ->update(['language' => 'ru']);
        
        echo "✅ Обновлено заказов: $updated\n\n";
    } else {
        echo "✅ Заказов с language='en' не найдено\n";
    }
    
    // Проверяем также 'EN' (верхний регистр)
    $ordersEN = DB::table('orders')->where('language', 'EN')->count();
    if ($ordersEN > 0) {
        $updatedEN = DB::table('orders')
            ->where('language', 'EN')
            ->update(['language' => 'ru']);
        echo "✅ Обновлено заказов с 'EN': $updatedEN\n\n";
    }
    
    // Проверяем NULL или пустые значения
    $ordersNull = DB::table('orders')->whereNull('language')->orWhere('language', '')->count();
    if ($ordersNull > 0) {
        $updatedNull = DB::table('orders')
            ->whereNull('language')
            ->orWhere('language', '')
            ->update(['language' => 'ru']);
        echo "✅ Обновлено заказов с NULL/пустым language: $updatedNull\n\n";
    }
    
    // Финальная статистика
    echo "=== Финальная статистика ===\n";
    $total = DB::table('orders')->count();
    $ru = DB::table('orders')->where('language', 'ru')->count();
    $en = DB::table('orders')->where('language', 'en')->count();
    $null = DB::table('orders')->whereNull('language')->orWhere('language', '')->count();
    
    echo "Всего заказов: $total\n";
    echo "С language='ru': $ru\n";
    echo "С language='en': $en\n";
    echo "С language=NULL: $null\n";
    
    echo "\n=== Готово ===\n";
    
} catch (\Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
