<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Подключаем Laravel
    require_once __DIR__ . '/../vendor/autoload.php';
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    
    // Получаем язык
    $language = $_GET['language'] ?? 'ru';
    
    // Прямое подключение к базе
    $config = require __DIR__ . '/../config/database.php';
    $dbConfig = $config['connections']['mysql'];
    
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4",
        $dbConfig['username'],
        $dbConfig['password']
    );
    
    // Запрос категорий
    $stmt = $pdo->prepare("SELECT id, name, slug, parent, icon FROM categories WHERE language = ? ORDER BY name");
    $stmt->execute([$language]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $categories,
        'count' => count($categories)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
