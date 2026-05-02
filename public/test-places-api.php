<?php
// Тест API плейсов
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo "=== ТЕСТ API ПЛЕЙСОВ ===\n";

// 1. Проверяем подключение к базе данных
try {
    $pdo = new PDO('mysql:host=localhost;dbname=sancan', 'root', '');
    echo "✅ База данных подключена\n";
} catch (PDOException $e) {
    echo "❌ Ошибка подключения к БД: " . $e->getMessage() . "\n";
    exit;
}

// 2. Проверяем таблицу places
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM places");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Таблица places существует, записей: " . $result['count'] . "\n";
} catch (PDOException $e) {
    echo "❌ Ошибка таблицы places: " . $e->getMessage() . "\n";
    exit;
}

// 3. Получаем несколько плейсов
try {
    $stmt = $pdo->query("SELECT id, title, description, created_at FROM places ORDER BY created_at DESC LIMIT 5");
    $places = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Получено плейсов: " . count($places) . "\n";
    
    foreach ($places as $place) {
        echo "  - ID: {$place['id']}, Title: {$place['title']}\n";
    }
} catch (PDOException $e) {
    echo "❌ Ошибка получения плейсов: " . $e->getMessage() . "\n";
}

// 4. Проверяем связанные таблицы
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM place_images");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Таблица place_images, записей: " . $result['count'] . "\n";
} catch (PDOException $e) {
    echo "❌ Ошибка таблицы place_images: " . $e->getMessage() . "\n";
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM place_videos");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Таблица place_videos, записей: " . $result['count'] . "\n";
} catch (PDOException $e) {
    echo "❌ Ошибка таблицы place_videos: " . $e->getMessage() . "\n";
}

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";
?> 