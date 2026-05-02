<?php

/**
 * Готовый скрипт для проверки токена из cookie
 * Использование: php check-token.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Crypt;

echo "=== ПРОВЕРКА ТОКЕНА ===\n\n";

// Токен из cookie браузера
$encryptedToken = 'eyJpdiI6IkpjdUlDUlM4UXRraW9na3E3VHB5YlE9PSIsInZhbHVlIjoiSXRtdkNNR3dzNFRydTZuampNaGhUZEhaaU5xdjEvNVUxelhvQkIzYWtlVFZTMUhPc0pGS3FxYkYwMiswbG1zUXMyczRub0RlR3NOb255a0lxZGxuakh0czdkNjlEUVJHSWxEbXp6VVRNVnIwdlNEeVo1TmNYWDhibzlKVmUvTzYiLCJtYWMiOiJlOTVkZmFhMTFhZmZlNmJiMTEwNDZiZjUyYjI0OGJhOGY2NmMwOTllNmMwMjJhZjI3NDFjZWZiNjEwZGFjZjRlIiwidGFnIjoiIn0';

echo "1. Исходный токен (зашифрованный):\n";
echo "   " . substr($encryptedToken, 0, 50) . "...\n";
echo "   Длина: " . strlen($encryptedToken) . " символов\n\n";

// Пробуем расшифровать токен
echo "2. Попытка расшифровки токена...\n";
try {
    $decryptedToken = Crypt::decryptString($encryptedToken);
    echo "   ✅ Токен расшифрован!\n";
    echo "   Расшифрованный токен: " . substr($decryptedToken, 0, 50) . "...\n";
    echo "   Длина: " . strlen($decryptedToken) . " символов\n\n";
    
    $token = $decryptedToken;
} catch (\Exception $e) {
    echo "   ❌ Ошибка расшифровки: {$e->getMessage()}\n";
    echo "   Пробуем использовать токен как есть...\n\n";
    $token = $encryptedToken;
}

// Проверяем формат токена
echo "3. Проверка формата токена...\n";
$parts = explode('|', $token);
echo "   Частей в токене: " . count($parts) . "\n";
foreach ($parts as $i => $part) {
    echo "   - Часть {$i}: " . substr($part, 0, 30) . "...\n";
}
echo "\n";

if (count($parts) == 2) {
    echo "   ✅ Правильный формат Sanctum: {id}|{token}\n";
    $tokenId = $parts[0];
    $tokenHash = $parts[1];
} elseif (count($parts) == 3) {
    echo "   ⚠️ Неправильный формат: {hash}|{id}|{token}\n";
    echo "   Пробуем использовать правильный формат: {id}|{token}\n";
    $tokenId = $parts[1]; // ID - это вторая часть
    $tokenHash = $parts[2]; // Token - это третья часть
    $token = $tokenId . '|' . $tokenHash; // Правильный формат
    echo "   Правильный токен: " . substr($token, 0, 50) . "...\n\n";
} else {
    echo "   ❌ Неправильный формат токена!\n";
    echo "   Ожидается: {id}|{token}\n\n";
}

// Ищем токен в БД
echo "4. Поиск токена в БД...\n";
try {
    $tokenModel = PersonalAccessToken::findToken($token);
    
    if ($tokenModel) {
        echo "   ✅ Токен найден в БД!\n";
        echo "   - ID токена: {$tokenModel->id}\n";
        echo "   - Имя токена: {$tokenModel->name}\n";
        echo "   - ID пользователя: {$tokenModel->tokenable_id}\n";
        echo "   - Тип: {$tokenModel->tokenable_type}\n";
        
        $user = $tokenModel->tokenable;
        if ($user) {
            echo "   - Пользователь: {$user->name} ({$user->email})\n";
            echo "   - Email verified: " . ($user->hasVerifiedEmail() ? 'YES' : 'NO') . "\n";
        } else {
            echo "   ❌ Пользователь не найден!\n";
        }
    } else {
        echo "   ❌ Токен НЕ найден в БД!\n";
        
        // Пробуем найти по ID
        if (isset($tokenId)) {
            echo "\n   Пробуем найти токен по ID: {$tokenId}\n";
            $tokenById = PersonalAccessToken::find($tokenId);
            if ($tokenById) {
                echo "   ✅ Токен найден по ID!\n";
                echo "   - ID токена: {$tokenById->id}\n";
                echo "   - Имя: {$tokenById->name}\n";
                echo "   - Пользователь ID: {$tokenById->tokenable_id}\n";
                
                $user = $tokenById->tokenable;
                if ($user) {
                    echo "   - Пользователь: {$user->name} ({$user->email})\n";
                    echo "   - Email verified: " . ($user->hasVerifiedEmail() ? 'YES' : 'NO') . "\n";
                }
            } else {
                echo "   ❌ Токен не найден даже по ID\n";
            }
        }
    }
} catch (\Exception $e) {
    echo "   ❌ Ошибка при поиске токена: {$e->getMessage()}\n";
}

echo "\n=== ПРОВЕРКА ЗАВЕРШЕНА ===\n";

