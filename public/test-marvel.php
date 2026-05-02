<?php
// Тест Marvel пакета
echo "<h1>Тест Marvel пакета</h1>";

// Проверяем, загружен ли Marvel
if (class_exists('Marvel\ShopServiceProvider')) {
    echo "<p style='color: green;'>✅ Marvel пакет загружен</p>";
} else {
    echo "<p style='color: red;'>❌ Marvel пакет НЕ загружен</p>";
}

// Проверяем маршруты
echo "<h2>Доступные маршруты:</h2>";
$routes = [];
if (function_exists('app')) {
    try {
        $router = app('router');
        $routes = $router->getRoutes();
        echo "<p>Найдено маршрутов: " . count($routes) . "</p>";
        
        // Показываем только API маршруты
        echo "<h3>API маршруты:</h3>";
        foreach ($routes as $route) {
            $uri = $route->uri();
            if (strpos($uri, 'api/') === 0) {
                echo "<p>• {$route->methods()[0]} {$uri}</p>";
            }
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Ошибка при получении маршрутов: " . $e->getMessage() . "</p>";
    }
}

// Проверяем конфигурацию
echo "<h2>Конфигурация Marvel:</h2>";
if (function_exists('config')) {
    try {
        $shopConfig = config('shop');
        if ($shopConfig) {
            echo "<p style='color: green;'>✅ Конфигурация shop загружена</p>";
            echo "<p>SHOP_URL: " . ($shopConfig['shop_url'] ?? 'не установлен') . "</p>";
            echo "<p>DASHBOARD_URL: " . ($shopConfig['dashboard_url'] ?? 'не установлен') . "</p>";
        } else {
            echo "<p style='color: red;'>❌ Конфигурация shop НЕ загружена</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Ошибка при получении конфигурации: " . $e->getMessage() . "</p>";
    }
}

// Проверяем базу данных
echo "<h2>База данных:</h2>";
try {
    $pdo = new PDO('mysql:host=localhost;dbname=marvel_laravel', 'marvel_laravel', 'T123456s');
    echo "<p style='color: green;'>✅ Подключение к БД успешно</p>";
    
    // Проверяем таблицы Marvel
    $tables = ['users', 'products', 'categories', 'orders'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<p>✅ Таблица $table существует</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Таблица $table НЕ найдена</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Ошибка подключения к БД: " . $e->getMessage() . "</p>";
}

echo "<h2>PHP Info:</h2>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<p>Laravel Version: " . (defined('LARAVEL_VERSION') ? LARAVEL_VERSION : 'не определен') . "</p>";
?> 