<?php

require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__FILE__))
    ->withRouting(
        web: __DIR__.'/routes/web.php',
        api: __DIR__.'/routes/api.php',
        commands: __DIR__.'/routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Получаем все маршруты
$routes = Route::getRoutes();

echo "Доступные маршруты:\n";
echo "==================\n\n";

foreach ($routes as $route) {
    $methods = implode('|', $route->methods());
    $uri = $route->uri();
    
    // Фильтруем только API маршруты
    if (strpos($uri, 'api/') === 0) {
        echo sprintf("%-10s %s\n", $methods, $uri);
    }
}

echo "\nМаршруты плейсов:\n";
echo "=================\n\n";

foreach ($routes as $route) {
    $methods = implode('|', $route->methods());
    $uri = $route->uri();
    
    // Ищем маршруты плейсов
    if (strpos($uri, 'places') !== false) {
        echo sprintf("%-10s %s\n", $methods, $uri);
    }
} 