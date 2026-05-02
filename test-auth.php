<?php

/**
 * Тестовый скрипт для проверки авторизации
 * Использование: php test-auth.php <email> <password>
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

echo "=== ТЕСТ АВТОРИЗАЦИИ ===\n\n";

// Получаем аргументы командной строки
$email = $argv[1] ?? null;
$password = $argv[2] ?? null;

if (!$email || !$password) {
    echo "Использование: php test-auth.php <email> <password>\n";
    exit(1);
}

echo "1. Проверка пользователя в БД...\n";
$user = User::where('email', $email)->first();
if (!$user) {
    echo "❌ Пользователь не найден\n";
    exit(1);
}
echo "✅ Пользователь найден: ID={$user->id}, Name={$user->name}\n\n";

echo "2. Попытка входа через API /token...\n";
// Используем правильный URL для продакшена
$baseUrl = env('APP_URL', 'https://api.sancan.ru');
if (substr($baseUrl, -4) !== '/api') {
    $baseUrl = rtrim($baseUrl, '/') . '/api';
}
echo "   Base URL: {$baseUrl}\n";
$loginResponse = Http::post("{$baseUrl}/token", [
    'email' => $email,
    'password' => $password,
]);

echo "   Status: {$loginResponse->status()}\n";
echo "   Response: " . json_encode($loginResponse->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if ($loginResponse->status() !== 200 || !$loginResponse->json('token')) {
    echo "❌ Ошибка входа\n";
    exit(1);
}

$token = $loginResponse->json('token');
echo "✅ Токен получен: " . substr($token, 0, 20) . "...\n\n";

echo "3. Проверка токена в БД (personal_access_tokens)...\n";
$dbToken = PersonalAccessToken::findToken($token);
if (!$dbToken) {
    echo "❌ Токен не найден в БД\n";
    exit(1);
}
echo "✅ Токен найден в БД:\n";
echo "   - ID: {$dbToken->id}\n";
echo "   - Tokenable Type: {$dbToken->tokenable_type}\n";
echo "   - Tokenable ID: {$dbToken->tokenable_id}\n";
echo "   - Name: {$dbToken->name}\n";
echo "   - Abilities: " . json_encode($dbToken->abilities) . "\n";
echo "   - Last Used At: " . ($dbToken->last_used_at ? $dbToken->last_used_at->toDateTimeString() : 'null') . "\n";
echo "   - Expires At: " . ($dbToken->expires_at ? $dbToken->expires_at->toDateTimeString() : 'null') . "\n\n";

echo "4. Проверка связи токена с пользователем...\n";
$tokenUser = $dbToken->tokenable;
if (!$tokenUser || $tokenUser->id !== $user->id) {
    echo "❌ Токен не связан с пользователем\n";
    exit(1);
}
echo "✅ Токен связан с пользователем: {$tokenUser->name} (ID: {$tokenUser->id})\n\n";

echo "5. Запрос к /me с токеном...\n";
echo "   URL: {$baseUrl}/me\n";
echo "   Token: " . substr($token, 0, 20) . "...\n";
$meResponse = Http::withHeaders([
    'Authorization' => "Bearer {$token}",
    'Accept' => 'application/json',
])->get("{$baseUrl}/me");

echo "   Status: {$meResponse->status()}\n";
if ($meResponse->status() === 200) {
    $meData = $meResponse->json();
    echo "✅ Запрос успешен:\n";
    echo "   - ID: " . ($meData['id'] ?? 'N/A') . "\n";
    echo "   - Name: " . ($meData['name'] ?? 'N/A') . "\n";
    echo "   - Email: " . ($meData['email'] ?? 'N/A') . "\n";
} else {
    echo "❌ Ошибка запроса:\n";
    echo "   Response: " . $meResponse->body() . "\n";
}

echo "\n6. Проверка через auth('api')...\n";
try {
    $testUser = auth('api')->setToken($token)->user();
    if ($testUser) {
        echo "✅ auth('api') работает: {$testUser->name} (ID: {$testUser->id})\n";
    } else {
        echo "❌ auth('api') не вернул пользователя\n";
    }
} catch (\Exception $e) {
    echo "❌ Ошибка auth('api'): {$e->getMessage()}\n";
}

echo "\n7. Проверка через auth('sanctum')...\n";
try {
    $testUser = auth('sanctum')->setToken($token)->user();
    if ($testUser) {
        echo "✅ auth('sanctum') работает: {$testUser->name} (ID: {$testUser->id})\n";
    } else {
        echo "❌ auth('sanctum') не вернул пользователя\n";
    }
} catch (\Exception $e) {
    echo "❌ Ошибка auth('sanctum'): {$e->getMessage()}\n";
}

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";


