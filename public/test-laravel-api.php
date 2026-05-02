<?php
// Тест Laravel API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo "=== ТЕСТ LARAVEL API ===\n";

// Проверяем, что Laravel загружается
try {
    require_once __DIR__ . '/../vendor/autoload.php';
    echo "✅ Composer autoload загружен\n";
} catch (Exception $e) {
    echo "❌ Ошибка загрузки autoload: " . $e->getMessage() . "\n";
    exit;
}

try {
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    echo "✅ Laravel приложение загружено\n";
} catch (Exception $e) {
    echo "❌ Ошибка загрузки Laravel: " . $e->getMessage() . "\n";
    exit;
}

try {
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    echo "✅ Laravel kernel загружен\n";
} catch (Exception $e) {
    echo "❌ Ошибка загрузки kernel: " . $e->getMessage() . "\n";
    exit;
}

// Проверяем подключение к базе данных
try {
    $db = $app->make('db');
    $connection = $db->connection();
    $connection->getPdo();
    echo "✅ База данных подключена\n";
} catch (Exception $e) {
    echo "❌ Ошибка подключения к БД: " . $e->getMessage() . "\n";
    exit;
}

// Проверяем модель Place
try {
    $placeModel = new \Marvel\Database\Models\Place();
    echo "✅ Модель Place загружена\n";
} catch (Exception $e) {
    echo "❌ Ошибка загрузки модели Place: " . $e->getMessage() . "\n";
    exit;
}

// Получаем плейсы
try {
    $places = $placeModel->with(['images', 'videos', 'hashtags', 'user', 'likes', 'products'])->latest()->limit(5)->get();
    echo "✅ Получено плейсов: " . $places->count() . "\n";
    
    foreach ($places as $place) {
        echo "  - ID: {$place->id}, Title: {$place->title}\n";
    }
} catch (Exception $e) {
    echo "❌ Ошибка получения плейсов: " . $e->getMessage() . "\n";
}

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";
?> 