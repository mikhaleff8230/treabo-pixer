<?php
// Тест API эндпоинта /places
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo "=== ТЕСТ API ЭНДПОИНТА /PLACES ===\n";

// Проверяем, что Laravel загружается
try {
    require_once __DIR__ . '/../vendor/autoload.php';
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    echo "✅ Laravel загружен\n";
} catch (Exception $e) {
    echo "❌ Ошибка загрузки Laravel: " . $e->getMessage() . "\n";
    exit;
}

// Создаем HTTP запрос
try {
    $request = Illuminate\Http\Request::create('/places', 'GET');
    $request->headers->set('Accept', 'application/json');
    
    // Получаем роутер
    $router = $app->make('router');
    
    // Находим роут для /places
    $routes = $router->getRoutes();
    $placesRoute = null;
    
    foreach ($routes as $route) {
        if ($route->uri() === 'places' && in_array('GET', $route->methods())) {
            $placesRoute = $route;
            break;
        }
    }
    
    if ($placesRoute) {
        echo "✅ Роут /places найден\n";
        echo "  - Методы: " . implode(', ', $placesRoute->methods()) . "\n";
        echo "  - Контроллер: " . $placesRoute->getActionName() . "\n";
    } else {
        echo "❌ Роут /places не найден\n";
        echo "Доступные роуты:\n";
        foreach ($routes as $route) {
            if (strpos($route->uri(), 'places') !== false) {
                echo "  - " . $route->uri() . " (" . implode(', ', $route->methods()) . ")\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка проверки роута: " . $e->getMessage() . "\n";
}

// Проверяем контроллер
try {
    $controller = new \Marvel\Http\Controllers\PlaceController();
    echo "✅ PlaceController загружен\n";
} catch (Exception $e) {
    echo "❌ Ошибка загрузки PlaceController: " . $e->getMessage() . "\n";
}

// Проверяем ресурс
try {
    $placeModel = new \Marvel\Database\Models\Place();
    $place = $placeModel->with(['images', 'videos', 'hashtags', 'user', 'likes', 'products'])->first();
    
    if ($place) {
        $resource = new \Marvel\Http\Resources\PlaceResource($place);
        $data = $resource->toArray(request());
        echo "✅ PlaceResource работает\n";
        echo "  - Данные плейса ID {$place->id}: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "❌ Нет плейсов в базе данных\n";
    }
} catch (Exception $e) {
    echo "❌ Ошибка PlaceResource: " . $e->getMessage() . "\n";
}

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";
?> 