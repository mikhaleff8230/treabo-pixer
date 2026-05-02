<?php
// Тестовый файл для проверки API endpoint categories/menu

// Устанавливаем заголовки для CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Подключаем автозагрузчик Laravel
require_once __DIR__ . '/../vendor/autoload.php';

// Загружаем приложение Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';

try {
    // Создаем экземпляр контроллера
    $controller = new \Marvel\Http\Controllers\CategoryController(
        new \Marvel\Database\Repositories\CategoryRepository()
    );
    
    // Создаем mock Request объект
    $request = new \Illuminate\Http\Request();
    $request->merge(['language' => 'ru']);
    
    // Вызываем метод
    $result = $controller->getMenuCategories($request);
    
    // Возвращаем результат
    echo json_encode([
        'success' => true,
        'data' => $result,
        'message' => 'Categories loaded successfully'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Возвращаем ошибку
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
