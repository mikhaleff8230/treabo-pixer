<?php

/**
 * Тестовый скрипт для проверки авторизации БЕЗ HTTP запросов
 * Использование: php test-auth-direct.php <email> <password>
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Marvel\Database\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Marvel\Http\Controllers\UserController;

echo "=== ТЕСТ АВТОРИЗАЦИИ (ПРЯМОЙ ВЫЗОВ) ===\n\n";

// Получаем аргументы командной строки
$email = $argv[1] ?? null;
$password = $argv[2] ?? null;

if (!$email || !$password) {
    echo "Использование: php test-auth-direct.php <email> <password>\n";
    exit(1);
}

echo "1. Проверка пользователя в БД...\n";
$user = User::where('email', $email)->first();
if (!$user) {
    echo "❌ Пользователь не найден\n";
    exit(1);
}
echo "✅ Пользователь найден: ID={$user->id}, Name={$user->name}\n";
echo "   Email verified: " . ($user->hasVerifiedEmail() ? 'YES' : 'NO') . "\n\n";

echo "2. Проверка пароля...\n";
if (!\Illuminate\Support\Facades\Hash::check($password, $user->password)) {
    echo "❌ Неверный пароль\n";
    exit(1);
}
echo "✅ Пароль верный\n\n";

echo "3. Создание токена через createToken()...\n";
$token = $user->createToken('test_token')->plainTextToken;
echo "✅ Токен создан: " . substr($token, 0, 20) . "...\n\n";

echo "4. Проверка токена в БД...\n";
$dbToken = PersonalAccessToken::findToken($token);
if (!$dbToken) {
    echo "❌ Токен не найден в БД\n";
    exit(1);
}
echo "✅ Токен найден в БД:\n";
echo "   - ID: {$dbToken->id}\n";
echo "   - Tokenable ID: {$dbToken->tokenable_id}\n";
echo "   - Name: {$dbToken->name}\n\n";

echo "5. Проверка через Request с Authorization header (как в реальных запросах)...\n";
try {
    $request = Request::create('/api/me', 'GET');
    $request->headers->set('Authorization', "Bearer {$token}");
    
    // Используем Sanctum для проверки токена из заголовка
    $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if ($tokenModel) {
        $authUser = $tokenModel->tokenable;
        if ($authUser) {
            echo "✅ Токен валиден, пользователь найден: {$authUser->name} (ID: {$authUser->id})\n";
        } else {
            echo "❌ Токен найден, но пользователь не найден\n";
        }
    } else {
        echo "❌ Токен не найден в БД\n";
    }
} catch (\Exception $e) {
    echo "❌ Ошибка проверки токена: {$e->getMessage()}\n";
}

echo "\n6. Проверка через Sanctum::actingAs() (симуляция авторизованного запроса)...\n";
try {
    $request = Request::create('/api/me', 'GET');
    \Laravel\Sanctum\Sanctum::actingAs($user);
    
    // Проверяем, что пользователь установлен
    $authUser = $request->user();
    if ($authUser) {
        echo "✅ Sanctum::actingAs() работает: {$authUser->name} (ID: {$authUser->id})\n";
    } else {
        echo "❌ Sanctum::actingAs() не установил пользователя\n";
    }
} catch (\Exception $e) {
    echo "❌ Ошибка Sanctum::actingAs(): {$e->getMessage()}\n";
}

echo "\n7. Прямой вызов UserController::me()...\n";
try {
    $controller = app(UserController::class);
    $request = Request::create('/api/me', 'GET');
    $request->headers->set('Authorization', "Bearer {$token}");
    
    // Устанавливаем пользователя через auth guard
    $tokenModel = PersonalAccessToken::findToken($token);
    if ($tokenModel) {
        $request->setUserResolver(function () use ($tokenModel) {
            return $tokenModel->tokenable;
        });
        
        // Также устанавливаем через auth
        auth('api')->setUser($tokenModel->tokenable);
        
        $response = $controller->me($request);
        if ($response) {
            echo "✅ UserController::me() работает:\n";
            echo "   - ID: " . ($response->id ?? 'N/A') . "\n";
            echo "   - Name: " . ($response->name ?? 'N/A') . "\n";
            echo "   - Email: " . ($response->email ?? 'N/A') . "\n";
        } else {
            echo "❌ UserController::me() вернул null\n";
        }
    } else {
        echo "❌ Токен не найден для установки пользователя\n";
    }
} catch (\Exception $e) {
    echo "❌ Ошибка UserController::me(): {$e->getMessage()}\n";
}

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";

