<?php

use Illuminate\Support\Facades\Route;
use Marvel\Http\Controllers\YmlFeedController;
use Marvel\Http\Controllers\SitemapController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [\App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::post('/set-city', [\App\Http\Controllers\HomeController::class, 'setCity'])->name('set-city');

Route::get('/ping', function () {
    return response()->json(['status' => 'ok']);
});

// Интерфейс тестирования
//Route::get('/test-yookassa', function () {
//    ob_start();
//    require public_path('test-yookassa-interface.php');
//    return ob_get_clean();
//});

//Route::get('/api/test-yookassa', function () {
//    ob_start();
//    require public_path('test-yookassa-interface.php');
//    return ob_get_clean();
//});

// API для тестов
//Route::get('/test-yookassa-cases.php', function () {
//    return require public_path('test-yookassa-cases.php');
//});

// Добавляем обработку ошибок
//if (config('app.debug')) {
//   error_reporting(E_ALL);
//    ini_set('display_errors', 1);
//}

Route::get('/yml-feed', [YmlFeedController::class, 'index']);
Route::get('/yml-feed/{page?}', [YmlFeedController::class, 'index']);

Route::get('/sitemap.xml', [SitemapController::class, 'index']);

// Маршрут для отладки Тинькофф (только для продакшена)
Route::get('/debug/tinkoff', [App\Http\Controllers\Debug\TinkoffController::class, 'index'])->name('debug.tinkoff');

// Маршрут для тестирования Яндекс авторизации
Route::get('/debug/yandex-auth-test', [App\Http\Controllers\Debug\YandexAuthTestController::class, 'index'])->name('debug.yandex-auth-test');

// Маршрут для тестирования геолокации
// Route::get('/debug/geolocation-test', [App\Http\Controllers\Debug\GeoLocationTestController::class, 'index'])->name('debug.geolocation-test'); // ВРЕМЕННО ОТКЛЮЧЕНО

// Маршрут для страницы спасибо или нет Тинькофф

Route::view('/payment/success', 'payment.success');
Route::view('/payment/fail', 'payment.fail');

/*
|--------------------------------------------------------------------------
| Yandex OAuth 2.0 Routes
|--------------------------------------------------------------------------
|
| Маршруты для авторизации через Яндекс OAuth 2.0
|
*/

// Перенаправление на страницу авторизации Яндекса
Route::get('/auth/yandex', [Marvel\Http\Controllers\YandexAuthController::class, 'redirect'])
    ->name('auth.yandex');

// Обработка callback от Яндекса после авторизации
Route::get('/auth/yandex/callback', [Marvel\Http\Controllers\YandexAuthController::class, 'callback'])
    ->name('auth.yandex.callback');

// Получение данных текущего пользователя (для тестирования)
Route::get('/auth/yandex/user', [Marvel\Http\Controllers\YandexAuthController::class, 'user'])
    ->middleware('auth')
    ->name('auth.yandex.user');

// Получение данных пользователя из сессии (для автозаполнения форм)
Route::get('/auth/yandex/user-data', [Marvel\Http\Controllers\YandexAuthController::class, 'userData'])
    ->name('auth.yandex.user-data');

// ВРЕМЕННЫЙ: Просмотр логов Яндекс OAuth (удалите после отладки!)
Route::get('/debug/yandex-logs', function() {
    $logFile = storage_path('logs/laravel.log');
    if (!file_exists($logFile)) {
        return response()->json(['error' => 'Log file not found: ' . $logFile], 404);
    }
    
    $logs = file_get_contents($logFile);
    $yandexLogs = [];
    $lines = explode("\n", $logs);
    
    foreach ($lines as $line) {
        if (stripos($line, 'Yandex OAuth') !== false) {
            $yandexLogs[] = $line;
        }
    }
    
    // Последние 100 строк
    $recentLogs = array_slice($yandexLogs, -100);
    
    return response()->json([
        'total_lines' => count($yandexLogs),
        'recent_lines' => count($recentLogs),
        'logs' => $recentLogs,
        'log_file' => $logFile,
        'file_exists' => file_exists($logFile),
        'file_size' => filesize($logFile),
    ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
})->name('debug.yandex-logs');
