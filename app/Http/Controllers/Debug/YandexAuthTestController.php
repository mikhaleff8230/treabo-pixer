<?php

namespace App\Http\Controllers\Debug;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Marvel\Database\Models\User;
use Marvel\Database\Models\Profile;

/**
 * Тестовый контроллер для проверки всех компонентов Яндекс авторизации
 */
class YandexAuthTestController extends Controller
{
    /**
     * Главная страница тестирования
     */
    public function index()
    {
        $results = [
            'timestamp' => now()->toDateTimeString(),
            'checks' => [],
            'errors' => [],
            'warnings' => [],
            'success' => true,
        ];

        // 1. Проверка конфигурации
        $results['checks']['configuration'] = $this->checkConfiguration();
        
        // 2. Проверка установленных пакетов
        $results['checks']['packages'] = $this->checkPackages();
        
        // 3. Проверка маршрутов
        $results['checks']['routes'] = $this->checkRoutes();
        
        // 4. Проверка базы данных
        $results['checks']['database'] = $this->checkDatabase();
        
        // 5. Проверка Socialite
        $results['checks']['socialite'] = $this->checkSocialite();
        
        // 6. Проверка сессий
        $results['checks']['sessions'] = $this->checkSessions();
        
        // 7. Проверка кэша
        $results['checks']['cache'] = $this->checkCache();
        
        // 8. Проверка переменных окружения
        $results['checks']['environment'] = $this->checkEnvironment();
        
        // 9. Проверка EventServiceProvider
        $results['checks']['event_provider'] = $this->checkEventProvider();
        
        // Собираем все ошибки и предупреждения
        foreach ($results['checks'] as $check) {
            if (isset($check['errors']) && !empty($check['errors'])) {
                $results['errors'] = array_merge($results['errors'], $check['errors']);
                $results['success'] = false;
            }
            if (isset($check['warnings']) && !empty($check['warnings'])) {
                $results['warnings'] = array_merge($results['warnings'], $check['warnings']);
            }
        }

        // Если запрос ожидает JSON, возвращаем JSON
        if (request()->expectsJson() || request()->wantsJson()) {
            return response()->json($results, $results['success'] ? 200 : 500);
        }

        // Иначе возвращаем HTML страницу
        return $this->renderHtml($results);
    }

    /**
     * Проверка конфигурации
     */
    private function checkConfiguration()
    {
        $result = [
            'name' => 'Конфигурация Яндекс OAuth',
            'status' => 'ok',
            'details' => [],
            'errors' => [],
            'warnings' => [],
        ];

        try {
            $clientId = config('services.yandex.client_id');
            $clientSecret = config('services.yandex.client_secret');
            $redirect = config('services.yandex.redirect');

            $result['details']['client_id'] = [
                'exists' => !empty($clientId),
                'value' => $clientId ? substr($clientId, 0, 10) . '...' : null,
                'length' => $clientId ? strlen($clientId) : 0,
            ];

            $result['details']['client_secret'] = [
                'exists' => !empty($clientSecret),
                'value' => $clientSecret ? substr($clientSecret, 0, 10) . '...' : null,
                'length' => $clientSecret ? strlen($clientSecret) : 0,
            ];

            $result['details']['redirect_uri'] = [
                'value' => $redirect,
                'expected' => 'https://sancan.ru/auth/yandex/callback',
            ];

            if (empty($clientId)) {
                $result['status'] = 'error';
                $result['errors'][] = 'YANDEX_CLIENT_ID не установлен в .env';
            }

            if (empty($clientSecret)) {
                $result['status'] = 'error';
                $result['errors'][] = 'YANDEX_CLIENT_SECRET не установлен в .env';
            }

            if ($redirect !== 'https://sancan.ru/auth/yandex/callback') {
                $result['warnings'][] = "Redirect URI не совпадает с ожидаемым. Текущий: {$redirect}";
            }

        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['errors'][] = 'Ошибка при проверке конфигурации: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Проверка установленных пакетов
     */
    private function checkPackages()
    {
        $result = [
            'name' => 'Установленные пакеты',
            'status' => 'ok',
            'details' => [],
            'errors' => [],
            'warnings' => [],
        ];

        try {
            // Проверяем composer.json
            $composerPath = base_path('composer.json');
            if (file_exists($composerPath)) {
                $composer = json_decode(file_get_contents($composerPath), true);
                $require = $composer['require'] ?? [];
                $requireDev = $composer['require-dev'] ?? [];

                $allPackages = array_merge($require, $requireDev);

                // Проверяем Laravel Socialite (обязательный)
                $result['details']['laravel/socialite'] = [
                    'installed' => isset($allPackages['laravel/socialite']),
                    'version' => $allPackages['laravel/socialite'] ?? 'не установлен',
                    'required' => true,
                ];

                // Проверяем SocialiteProviders Yandex (обязательный)
                $result['details']['socialiteproviders/yandex'] = [
                    'installed' => isset($allPackages['socialiteproviders/yandex']),
                    'version' => $allPackages['socialiteproviders/yandex'] ?? 'не установлен',
                    'required' => true,
                ];

                // Проверяем SocialiteProviders Manager (опциональный - не обязателен)
                $result['details']['socialiteproviders/manager'] = [
                    'installed' => isset($allPackages['socialiteproviders/manager']),
                    'version' => $allPackages['socialiteproviders/manager'] ?? 'не установлен',
                    'required' => false,
                ];

                if (!isset($allPackages['laravel/socialite'])) {
                    $result['status'] = 'error';
                    $result['errors'][] = 'Пакет laravel/socialite не установлен. Установите: composer require laravel/socialite';
                }

                if (!isset($allPackages['socialiteproviders/yandex'])) {
                    $result['status'] = 'error';
                    $result['errors'][] = 'Пакет socialiteproviders/yandex не установлен. Установите: composer require socialiteproviders/yandex';
                }

                // socialiteproviders/manager не обязателен - только предупреждение
                if (!isset($allPackages['socialiteproviders/manager'])) {
                    $result['warnings'][] = 'Пакет socialiteproviders/manager не установлен (не критично, пакет socialiteproviders/yandex работает независимо)';
                }
            } else {
                $result['status'] = 'warning';
                $result['warnings'][] = 'Файл composer.json не найден';
            }

        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['errors'][] = 'Ошибка при проверке пакетов: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Проверка маршрутов
     */
    private function checkRoutes()
    {
        $result = [
            'name' => 'Маршруты',
            'status' => 'ok',
            'details' => [],
            'errors' => [],
            'warnings' => [],
        ];

        try {
            $routesToCheck = [
                'auth.yandex' => '/auth/yandex',
                'auth.yandex.callback' => '/auth/yandex/callback',
                'auth.yandex.user-data' => '/auth/yandex/user-data',
            ];

            $allRoutes = Route::getRoutes();
            
            foreach ($routesToCheck as $routeName => $uri) {
                $route = null;
                
                // Пробуем найти по имени
                try {
                    $route = $allRoutes->getByName($routeName);
                } catch (\Exception $e) {
                    // Игнорируем, если маршрут не найден по имени
                }
                
                // Если не нашли по имени, пробуем найти по URI
                if (!$route) {
                    try {
                        $request = Request::create($uri, 'GET');
                        $route = $allRoutes->match($request);
                    } catch (\Exception $e) {
                        // Игнорируем, если маршрут не найден по URI
                    }
                }
                
                // Если все еще не нашли, проверяем все маршруты по URI
                if (!$route) {
                    foreach ($allRoutes as $r) {
                        $routeUri = $r->uri();
                        $checkUri = ltrim($uri, '/');
                        
                        // Проверяем точное совпадение или совпадение без параметров
                        if ($routeUri === $checkUri || 
                            $routeUri === str_replace('/', '\/', $checkUri) ||
                            strpos($routeUri, $checkUri) !== false) {
                            $route = $r;
                            break;
                        }
                    }
                }
                
                // Дополнительная проверка: пробуем найти через список всех маршрутов
                if (!$route) {
                    try {
                        $routeList = \Artisan::call('route:list', ['--json' => true]);
                        $routes = json_decode(\Artisan::output(), true);
                        if ($routes) {
                            foreach ($routes as $r) {
                                if (isset($r['uri']) && $r['uri'] === ltrim($uri, '/')) {
                                    // Маршрут существует, но не найден через Route::getRoutes()
                                    $result['warnings'][] = "Маршрут {$uri} существует, но не найден через Route::getRoutes()";
                                    break;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // Игнорируем ошибки при вызове artisan
                    }
                }

                $result['details'][$routeName] = [
                    'exists' => $route !== null,
                    'uri' => $route ? $route->uri() : $uri,
                    'methods' => $route ? $route->methods() : [],
                    'name' => $route ? ($route->getName() ?? 'unnamed') : 'not found',
                ];

                if (!$route) {
                    $result['status'] = 'error';
                    $result['errors'][] = "Маршрут {$uri} (name: {$routeName}) не найден. Проверьте, что маршруты зарегистрированы в routes/web.php";
                    
                    // Дополнительная информация для отладки
                    $result['details'][$routeName]['debug_info'] = [
                        'suggestion' => 'Убедитесь, что маршруты зарегистрированы в routes/web.php и кэш маршрутов очищен (php artisan route:clear)',
                        'expected_route' => "Route::get('{$uri}', [Marvel\\Http\\Controllers\\YandexAuthController::class, '...'])->name('{$routeName}');",
                    ];
                }
            }

        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['errors'][] = 'Ошибка при проверке маршрутов: ' . $e->getMessage();
            $result['details']['exception'] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }

        return $result;
    }

    /**
     * Проверка базы данных
     */
    private function checkDatabase()
    {
        $result = [
            'name' => 'База данных',
            'status' => 'ok',
            'details' => [],
            'errors' => [],
            'warnings' => [],
        ];

        try {
            // Проверяем подключение
            DB::connection()->getPdo();
            $result['details']['connection'] = ['status' => 'ok'];

            // Проверяем таблицы
            // Примечание: таблица называется 'providers', а не 'user_providers'
            $tables = ['users', 'user_profiles', 'providers'];
            foreach ($tables as $table) {
                try {
                    $exists = DB::getSchemaBuilder()->hasTable($table);
                    $result['details']['tables'][$table] = [
                        'exists' => $exists,
                    ];

                    if (!$exists) {
                        $result['status'] = 'error';
                        $result['errors'][] = "Таблица {$table} не существует";
                    } else {
                        // Проверяем структуру таблицы users
                        if ($table === 'users') {
                            $columns = DB::getSchemaBuilder()->getColumnListing($table);
                            $requiredColumns = ['id', 'email', 'name', 'password'];
                            foreach ($requiredColumns as $col) {
                                if (!in_array($col, $columns)) {
                                    $result['warnings'][] = "В таблице users отсутствует колонка {$col}";
                                }
                            }
                        }

                        // Проверяем структуру таблицы user_profiles
                        if ($table === 'user_profiles') {
                            $columns = DB::getSchemaBuilder()->getColumnListing($table);
                            if (!in_array('customer_id', $columns)) {
                                $result['errors'][] = "В таблице user_profiles отсутствует колонка customer_id";
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $result['details']['tables'][$table] = [
                        'exists' => false,
                        'error' => $e->getMessage(),
                    ];
                    $result['status'] = 'error';
                    $result['errors'][] = "Ошибка при проверке таблицы {$table}: " . $e->getMessage();
                }
            }

        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['errors'][] = 'Ошибка подключения к базе данных: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Проверка Socialite
     */
    private function checkSocialite()
    {
        $result = [
            'name' => 'Socialite',
            'status' => 'ok',
            'details' => [],
            'errors' => [],
            'warnings' => [],
        ];

        try {
            // Пробуем создать экземпляр драйвера
            try {
                $driver = Socialite::driver('yandex');
                $result['details']['driver_instance'] = [
                    'created' => true,
                    'class' => get_class($driver),
                ];
                
                // Проверяем конфигурацию драйвера
                $config = config('services.yandex');
                $result['details']['driver_config'] = [
                    'client_id_set' => !empty($config['client_id'] ?? null),
                    'client_secret_set' => !empty($config['client_secret'] ?? null),
                    'redirect_set' => !empty($config['redirect'] ?? null),
                ];
            } catch (\Exception $e) {
                $result['status'] = 'error';
                $result['errors'][] = 'Не удалось создать экземпляр драйвера yandex: ' . $e->getMessage();
                $result['details']['driver_error'] = [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
            }

        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['errors'][] = 'Ошибка при проверке Socialite: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Проверка сессий
     */
    private function checkSessions()
    {
        $result = [
            'name' => 'Сессии',
            'status' => 'ok',
            'details' => [],
            'errors' => [],
            'warnings' => [],
        ];

        try {
            $driver = config('session.driver');
            $result['details']['driver'] = $driver;

            // Пробуем сохранить и получить значение
            session(['yandex_test' => 'test_value']);
            $value = session('yandex_test');
            session()->forget('yandex_test');

            $result['details']['test_write_read'] = [
                'success' => $value === 'test_value',
                'value' => $value,
            ];

            if ($value !== 'test_value') {
                $result['status'] = 'warning';
                $result['warnings'][] = 'Сессии могут не работать корректно';
            }

        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['errors'][] = 'Ошибка при проверке сессий: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Проверка кэша
     */
    private function checkCache()
    {
        $result = [
            'name' => 'Кэш',
            'status' => 'ok',
            'details' => [],
            'errors' => [],
            'warnings' => [],
        ];

        try {
            $driver = config('cache.default');
            $result['details']['driver'] = $driver;

            // Пробуем сохранить и получить значение
            $testKey = 'yandex_test_' . time();
            Cache::put($testKey, 'test_value', 60);
            $value = Cache::get($testKey);
            Cache::forget($testKey);

            $result['details']['test_write_read'] = [
                'success' => $value === 'test_value',
                'value' => $value,
            ];

            if ($value !== 'test_value') {
                $result['status'] = 'warning';
                $result['warnings'][] = 'Кэш может не работать корректно';
            }

        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['errors'][] = 'Ошибка при проверке кэша: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Проверка переменных окружения
     */
    private function checkEnvironment()
    {
        $result = [
            'name' => 'Переменные окружения',
            'status' => 'ok',
            'details' => [],
            'errors' => [],
            'warnings' => [],
        ];

        try {
            $envVars = [
                'YANDEX_CLIENT_ID' => env('YANDEX_CLIENT_ID'),
                'YANDEX_CLIENT_SECRET' => env('YANDEX_CLIENT_SECRET'),
                'APP_URL' => env('APP_URL'),
                'SESSION_DRIVER' => env('SESSION_DRIVER'),
                'CACHE_DRIVER' => env('CACHE_DRIVER'),
            ];

            foreach ($envVars as $key => $value) {
                $result['details'][$key] = [
                    'exists' => !empty($value),
                    'value' => $key === 'YANDEX_CLIENT_SECRET' ? ($value ? '***' : null) : $value,
                ];

                if (in_array($key, ['YANDEX_CLIENT_ID', 'YANDEX_CLIENT_SECRET']) && empty($value)) {
                    $result['status'] = 'error';
                    $result['errors'][] = "Переменная окружения {$key} не установлена";
                }
            }

        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['errors'][] = 'Ошибка при проверке переменных окружения: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Проверка EventServiceProvider
     */
    private function checkEventProvider()
    {
        $result = [
            'name' => 'EventServiceProvider',
            'status' => 'ok',
            'details' => [],
            'errors' => [],
            'warnings' => [],
        ];

        try {
            $eventProviderPath = app_path('Providers/EventServiceProvider.php');
            $result['details']['file_exists'] = file_exists($eventProviderPath);

            if (file_exists($eventProviderPath)) {
                $content = file_get_contents($eventProviderPath);
                
                // Проверяем наличие регистрации Yandex провайдера
                $hasYandexExtend = strpos($content, 'YandexExtendSocialite') !== false;
                $hasSocialiteProviders = strpos($content, 'SocialiteProviders') !== false;
                
                $result['details']['yandex_extend_registered'] = $hasYandexExtend;
                $result['details']['socialiteproviders_imported'] = $hasSocialiteProviders;

                if (!$hasYandexExtend) {
                    $result['status'] = 'error';
                    $result['errors'][] = 'YandexExtendSocialite не зарегистрирован в EventServiceProvider';
                }
            } else {
                $result['status'] = 'warning';
                $result['warnings'][] = 'Файл EventServiceProvider.php не найден';
            }

        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['errors'][] = 'Ошибка при проверке EventServiceProvider: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Рендер HTML страницы
     */
    private function renderHtml($results)
    {
        $html = '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тест Яндекс авторизации</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header h1 {
            margin: 0;
            color: #333;
        }
        .summary {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .summary.success {
            border-left: 4px solid #10b981;
        }
        .summary.error {
            border-left: 4px solid #ef4444;
        }
        .check {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .check-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .check-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        .status {
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status.ok {
            background: #d1fae5;
            color: #065f46;
        }
        .status.error {
            background: #fee2e2;
            color: #991b1b;
        }
        .status.warning {
            background: #fef3c7;
            color: #92400e;
        }
        .details {
            background: #f9fafb;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
        }
        .details pre {
            margin: 0;
            font-size: 12px;
            overflow-x: auto;
        }
        .error-list, .warning-list {
            margin-top: 10px;
        }
        .error-list li {
            color: #dc2626;
            margin: 5px 0;
        }
        .warning-list li {
            color: #d97706;
            margin: 5px 0;
        }
        .timestamp {
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔍 Тест Яндекс авторизации</h1>
        <div class="timestamp">Время проверки: ' . $results['timestamp'] . '</div>
    </div>

    <div class="summary ' . ($results['success'] ? 'success' : 'error') . '">
        <h2>' . ($results['success'] ? '✅ Все проверки пройдены' : '❌ Обнаружены ошибки') . '</h2>';

        if (!empty($results['errors'])) {
            $html .= '<h3>Ошибки:</h3><ul class="error-list">';
            foreach ($results['errors'] as $error) {
                $html .= '<li>' . htmlspecialchars($error) . '</li>';
            }
            $html .= '</ul>';
        }

        if (!empty($results['warnings'])) {
            $html .= '<h3>Предупреждения:</h3><ul class="warning-list">';
            foreach ($results['warnings'] as $warning) {
                $html .= '<li>' . htmlspecialchars($warning) . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</div>';

        foreach ($results['checks'] as $check) {
            $html .= '<div class="check">
                <div class="check-header">
                    <div class="check-title">' . htmlspecialchars($check['name']) . '</div>
                    <span class="status ' . $check['status'] . '">' . $check['status'] . '</span>
                </div>';

            if (!empty($check['details'])) {
                $html .= '<div class="details">
                    <pre>' . htmlspecialchars(json_encode($check['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>
                </div>';
            }

            if (!empty($check['errors'])) {
                $html .= '<ul class="error-list">';
                foreach ($check['errors'] as $error) {
                    $html .= '<li>' . htmlspecialchars($error) . '</li>';
                }
                $html .= '</ul>';
            }

            if (!empty($check['warnings'])) {
                $html .= '<ul class="warning-list">';
                foreach ($check['warnings'] as $warning) {
                    $html .= '<li>' . htmlspecialchars($warning) . '</li>';
                }
                $html .= '</ul>';
            }

            $html .= '</div>';
        }

        $html .= '</body></html>';

        return $html;
    }
}

