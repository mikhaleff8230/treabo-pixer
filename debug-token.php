<?php

/**
 * Простой скрипт для проверки токена
 * Использование: php debug-token.php <token>
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Laravel\Sanctum\PersonalAccessToken;

echo "=== ПРОВЕРКА ТОКЕНА ===\n\n";

$token = $argv[1] ?? null;

if (!$token) {
    echo "Использование: php debug-token.php <token>\n";
    echo "Пример: php debug-token.php '1|abc123...'\n";
    exit(1);
}

echo "1. Токен получен: " . substr($token, 0, 50) . "...\n";
echo "   Длина токена: " . strlen($token) . " символов\n\n";

echo "2. Поиск токена в БД...\n";
try {
    $tokenModel = PersonalAccessToken::findToken($token);
    
    if ($tokenModel) {
        echo "✅ Токен найден в БД!\n";
        echo "   - ID токена: {$tokenModel->id}\n";
        echo "   - Имя токена: {$tokenModel->name}\n";
        echo "   - ID пользователя: {$tokenModel->tokenable_id}\n";
        echo "   - Тип: {$tokenModel->tokenable_type}\n";
        
        $user = $tokenModel->tokenable;
        if ($user) {
            echo "   - Пользователь: {$user->name} ({$user->email})\n";
        } else {
            echo "   ❌ Пользователь не найден!\n";
        }
    } else {
        echo "❌ Токен НЕ найден в БД!\n";
        echo "\n3. Проверка формата токена...\n";
        
        // Проверяем формат токена
        if (strpos($token, '|') !== false) {
            echo "   ✅ Токен содержит '|' (правильный формат Sanctum)\n";
            $parts = explode('|', $token, 2);
            echo "   - ID части: {$parts[0]}\n";
            echo "   - Token часть: " . substr($parts[1] ?? '', 0, 20) . "...\n";
            
            // Пробуем найти по ID
            $tokenById = PersonalAccessToken::find($parts[0]);
            if ($tokenById) {
                echo "   ✅ Токен найден по ID!\n";
                echo "   - Но findToken() не нашел - возможно проблема с хешированием\n";
            } else {
                echo "   ❌ Токен не найден даже по ID\n";
            }
        } else {
            echo "   ❌ Токен НЕ содержит '|' (неправильный формат!)\n";
            echo "   Ожидается формат: {id}|{token}\n";
            echo "   Получен формат: " . substr($token, 0, 100) . "...\n";
        }
    }
} catch (\Exception $e) {
    echo "❌ Ошибка при поиске токена: {$e->getMessage()}\n";
}

echo "\n=== ПРОВЕРКА ЗАВЕРШЕНА ===\n";

