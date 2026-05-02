<?php

/**
 * Отладочная страница проверки SMS верификации
 * 
 * Использование:
 * 1. Откройте в браузере: http://your-domain.com/debug-sms-verification.php
 * 2. Или через CLI: php debug-sms-verification.php
 * 
 * Параметры:
 * ?phone=+79031290826 - номер телефона для теста
 * ?action=send|verify|full - действие (send, verify, full)
 * ?otp_id=xxx - ID OTP для проверки
 * ?code=123456 - код для проверки
 */

// Инициализация Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Marvel\Otp\Gateways\OtpGateway;
use Marvel\Database\Models\Profile;
use Marvel\Database\Models\User;

// Определяем режим работы (CLI или Web)
$isCli = php_sapi_name() === 'cli';

// Функция для вывода
function output($message, $type = 'info', $cli = false) {
    if ($cli) {
        $colors = [
            'success' => "\033[32m",
            'error' => "\033[31m",
            'warning' => "\033[33m",
            'info' => "\033[36m",
            'debug' => "\033[37m",
            'reset' => "\033[0m"
        ];
        echo $colors[$type] . $message . $colors['reset'] . "\n";
    } else {
        $styles = [
            'success' => 'color: green; font-weight: bold;',
            'error' => 'color: red; font-weight: bold;',
            'warning' => 'color: orange; font-weight: bold;',
            'info' => 'color: blue;',
            'debug' => 'color: gray;',
        ];
        echo '<div style="' . ($styles[$type] ?? '') . '">' . htmlspecialchars($message) . '</div>';
    }
}

// Функция для вывода секции
function section($title, $cli = false) {
    if ($cli) {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "  $title\n";
        echo str_repeat("=", 60) . "\n\n";
    } else {
        echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border-left: 4px solid #007bff; font-weight: bold; font-size: 16px;">' . htmlspecialchars($title) . '</div>';
    }
}

// Функция для вывода данных
function dumpData($data, $title = '', $cli = false) {
    if ($cli) {
        if ($title) echo "$title:\n";
        print_r($data);
        echo "\n";
    } else {
        if ($title) echo '<strong>' . htmlspecialchars($title) . ':</strong><br>';
        echo '<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto;">';
        print_r($data);
        echo '</pre>';
    }
}

// Начало HTML (если не CLI)
if (!$isCli) {
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Отладка SMS верификации</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f9f9f9; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 Отладка SMS верификации</h1>
    <form method="GET" style="background: #f0f0f0; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
        <div class="form-group">
            <label>Телефон:</label>
            <input type="text" name="phone" value="' . htmlspecialchars($_GET['phone'] ?? '+79031290826') . '" placeholder="+79031290826">
        </div>
        <div class="form-group">
            <label>Действие:</label>
            <select name="action">
                <option value="full" ' . (($_GET['action'] ?? 'full') === 'full' ? 'selected' : '') . '>Полный тест</option>
                <option value="send" ' . (($_GET['action'] ?? '') === 'send' ? 'selected' : '') . '>Только отправка</option>
                <option value="verify" ' . (($_GET['action'] ?? '') === 'verify' ? 'selected' : '') . '>Только проверка</option>
                <option value="check" ' . (($_GET['action'] ?? '') === 'check' ? 'selected' : '') . '>Проверка конфигурации</option>
            </select>
        </div>
        <div class="form-group">
            <label>OTP ID (для проверки):</label>
            <input type="text" name="otp_id" value="' . htmlspecialchars($_GET['otp_id'] ?? '') . '" placeholder="xxx-xxx-xxx">
        </div>
        <div class="form-group">
            <label>Код (для проверки):</label>
            <input type="text" name="code" value="' . htmlspecialchars($_GET['code'] ?? '') . '" placeholder="123456">
        </div>
        <button type="submit">Запустить тест</button>
    </form>
    <hr>';
}

// Получаем параметры
$phone = $_GET['phone'] ?? ($argv[1] ?? '+79031290826');
$action = $_GET['action'] ?? ($argv[2] ?? 'full');
$otpId = $_GET['otp_id'] ?? ($argv[3] ?? null);
$code = $_GET['code'] ?? ($argv[4] ?? null);

// Массив для хранения результатов
$results = [];
$errors = [];

// ============================================
// ЭТАП 1: Проверка конфигурации
// ============================================
section("ЭТАП 1: Проверка конфигурации", $isCli);

try {
    $activeGateway = Config::get('auth.active_otp_gateway');
    output("Активный провайдер: $activeGateway", 'info', $isCli);
    $results['config']['active_gateway'] = $activeGateway;
    
    // Проверка конфигурации REDSMS
    if ($activeGateway === 'redsms') {
        $redsmsConfig = Config::get('services.redsms');
        output("REDSMS конфигурация:", 'info', $isCli);
        dumpData([
            'login' => $redsmsConfig['login'] ?? 'НЕ НАСТРОЕН',
            'api_key' => isset($redsmsConfig['api_key']) ? substr($redsmsConfig['api_key'], 0, 10) . '...' : 'НЕ НАСТРОЕН',
            'base_url' => $redsmsConfig['base_url'] ?? 'НЕ НАСТРОЕН',
            'sender' => $redsmsConfig['sender'] ?? 'НЕ НАСТРОЕН',
        ], '', $isCli);
        
        if (empty($redsmsConfig['login']) || empty($redsmsConfig['api_key'])) {
            $errors[] = "REDSMS конфигурация неполная!";
            output("❌ ОШИБКА: REDSMS конфигурация неполная!", 'error', $isCli);
        } else {
            output("✅ REDSMS конфигурация OK", 'success', $isCli);
        }
        $results['config']['redsms'] = $redsmsConfig;
    }
    
    // Проверка Twilio
    if ($activeGateway === 'twilio') {
        $twilioConfig = Config::get('services.twilio');
        if (empty($twilioConfig['account_sid']) || empty($twilioConfig['auth_token'])) {
            $errors[] = "Twilio конфигурация неполная!";
            output("❌ ОШИБКА: Twilio конфигурация неполная!", 'error', $isCli);
        } else {
            output("✅ Twilio конфигурация OK", 'success', $isCli);
        }
    }
    
    // Проверка MessageBird
    if ($activeGateway === 'messagebird') {
        $mbConfig = Config::get('services.messagebird');
        if (empty($mbConfig['api_key'])) {
            $errors[] = "MessageBird конфигурация неполная!";
            output("❌ ОШИБКА: MessageBird конфигурация неполная!", 'error', $isCli);
        } else {
            output("✅ MessageBird конфигурация OK", 'success', $isCli);
        }
    }
    
} catch (\Exception $e) {
    $errors[] = "Ошибка проверки конфигурации: " . $e->getMessage();
    output("❌ ОШИБКА: " . $e->getMessage(), 'error', $isCli);
}

// Проверка Cache
try {
    $cacheDriver = Config::get('cache.default');
    output("Cache драйвер: $cacheDriver", 'info', $isCli);
    
    // Тест записи в Cache
    Cache::put('test_sms_debug', 'test_value', 60);
    $testValue = Cache::get('test_sms_debug');
    if ($testValue === 'test_value') {
        output("✅ Cache работает", 'success', $isCli);
        Cache::forget('test_sms_debug');
    } else {
        $errors[] = "Cache не работает!";
        output("❌ ОШИБКА: Cache не работает!", 'error', $isCli);
    }
} catch (\Exception $e) {
    $errors[] = "Ошибка проверки Cache: " . $e->getMessage();
    output("❌ ОШИБКА Cache: " . $e->getMessage(), 'error', $isCli);
}

// Проверка БД
try {
    DB::connection()->getPdo();
    output("✅ Подключение к БД OK", 'success', $isCli);
} catch (\Exception $e) {
    $errors[] = "Ошибка подключения к БД: " . $e->getMessage();
    output("❌ ОШИБКА БД: " . $e->getMessage(), 'error', $isCli);
}

// Если только проверка конфигурации
if ($action === 'check') {
    if (!$isCli) {
        echo '</div></body></html>';
    }
    exit(0);
}

// ============================================
// ЭТАП 2: Отправка OTP
// ============================================
if ($action === 'full' || $action === 'send') {
    section("ЭТАП 2: Отправка OTP кода", $isCli);
    
    output("Телефон для теста: $phone", 'info', $isCli);
    
    try {
        // Создаем Gateway
        $gatewayClass = "Marvel\\Otp\\Gateways\\" . ucfirst($activeGateway) . 'Gateway';
        if (!class_exists($gatewayClass)) {
            throw new \Exception("Класс $gatewayClass не найден!");
        }
        
        output("Создание Gateway: $gatewayClass", 'debug', $isCli);
        $gateway = new $gatewayClass();
        $otpGateway = new OtpGateway($gateway);
        
        // Отправка OTP
        output("Отправка OTP кода...", 'info', $isCli);
        
        // Включаем логирование для отладки
        \Illuminate\Support\Facades\Log::info('DEBUG SMS: Starting OTP verification', [
            'phone' => $phone,
            'gateway' => $activeGateway
        ]);
        
        $result = $otpGateway->startVerification($phone);
        
        // Логируем результат
        \Illuminate\Support\Facades\Log::info('DEBUG SMS: OTP result', [
            'isValid' => $result->isValid(),
            'id' => $result->getId(),
            'errors' => $result->getErrors()
        ]);
        
        // Показываем полный ответ от API (для отладки)
        // Читаем последние логи Laravel
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            // Ищем последний лог REDSMS API Request
            if (preg_match('/REDSMS API Request.*?response.*?(\{.*?\})/s', $logContent, $matches)) {
                output("Последний ответ REDSMS API (из логов):", 'debug', $isCli);
                // Пытаемся распарсить JSON из логов
            }
        }
        
        if (!$result->isValid()) {
            $errorMsg = $result->getErrors();
            $errors[] = "Ошибка отправки OTP: " . (is_array($errorMsg) ? implode(', ', $errorMsg) : $errorMsg);
            output("❌ ОШИБКА отправки OTP", 'error', $isCli);
            dumpData($errorMsg, 'Детали ошибки', $isCli);
        } else {
            $otpId = $result->getId();
            output("✅ OTP код отправлен успешно!", 'success', $isCli);
            output("OTP ID: $otpId", 'info', $isCli);
            $results['send']['success'] = true;
            $results['send']['otp_id'] = $otpId;
            
            // Проверка Cache
            // Для REDSMS ключ: redsms_otp_{uuid}
            $cacheKey = strtolower($activeGateway) . "_otp_" . $otpId;
            $cachedData = Cache::get($cacheKey);
            
            // Дополнительная отладка: показываем полный ответ от API
            output("Полный ответ от REDSMS API:", 'debug', $isCli);
            try {
                $gatewayReflection = new ReflectionClass($gateway);
                // Пытаемся получить последний ответ (если есть метод для этого)
            } catch (\Exception $e) {
                // Игнорируем ошибки рефлексии
            }
            
            if ($cachedData) {
                output("✅ Данные найдены в Cache", 'success', $isCli);
                dumpData($cachedData, 'Данные в Cache', $isCli);
                $results['send']['cache_data'] = $cachedData;
                
                // Показываем код (для тестирования)
                if (isset($cachedData['code'])) {
                    output("⚠️  КОД ДЛЯ ТЕСТА: " . $cachedData['code'], 'warning', $isCli);
                    $results['send']['test_code'] = $cachedData['code'];
                }
            } else {
                output("⚠️  ВНИМАНИЕ: Данные не найдены в Cache!", 'warning', $isCli);
                output("Проверьте ключ Cache: $cacheKey", 'debug', $isCli);
            }
            
            // Проверка существования профиля
            $profile = Profile::where('contact', $phone)->first();
            if ($profile) {
                output("✅ Профиль найден в БД (ID: {$profile->id})", 'success', $isCli);
                $results['send']['profile_exists'] = true;
                $results['send']['profile_id'] = $profile->id;
            } else {
                output("ℹ️  Профиль не найден (новый пользователь)", 'info', $isCli);
                $results['send']['profile_exists'] = false;
            }
        }
        
    } catch (\Exception $e) {
        $errors[] = "Ошибка отправки OTP: " . $e->getMessage();
        output("❌ КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage(), 'error', $isCli);
        output("Stack trace:", 'debug', $isCli);
        dumpData($e->getTraceAsString(), '', $isCli);
    }
}

// ============================================
// ЭТАП 3: Проверка OTP кода
// ============================================
if (($action === 'full' || $action === 'verify') && $otpId && $code) {
    section("ЭТАП 3: Проверка OTP кода", $isCli);
    
    output("OTP ID: $otpId", 'info', $isCli);
    output("Введенный код: $code", 'info', $isCli);
    
    try {
        // Создаем Gateway
        $gatewayClass = "Marvel\\Otp\\Gateways\\" . ucfirst($activeGateway) . 'Gateway';
        $gateway = new $gatewayClass();
        $otpGateway = new OtpGateway($gateway);
        
        // Проверка кода
        output("Проверка кода...", 'info', $isCli);
        $result = $otpGateway->checkVerification($otpId, $code, $phone);
        
        if ($result->isValid()) {
            output("✅ Код верен! Верификация успешна!", 'success', $isCli);
            $results['verify']['success'] = true;
            
            // Проверяем, что код удален из Cache
            $cacheKey = strtolower($activeGateway) . "_otp_" . $otpId;
            $cachedData = Cache::get($cacheKey);
            if ($cachedData) {
                output("⚠️  ВНИМАНИЕ: Код все еще в Cache (должен быть удален)", 'warning', $isCli);
            } else {
                output("✅ Код удален из Cache (как и должно быть)", 'success', $isCli);
            }
        } else {
            $errorMsg = $result->getErrors();
            $errors[] = "Ошибка проверки кода: " . (is_array($errorMsg) ? implode(', ', $errorMsg) : $errorMsg);
            output("❌ Код неверен или истек", 'error', $isCli);
            dumpData($errorMsg, 'Детали ошибки', $isCli);
            $results['verify']['success'] = false;
            $results['verify']['error'] = $errorMsg;
        }
        
    } catch (\Exception $e) {
        $errors[] = "Ошибка проверки кода: " . $e->getMessage();
        output("❌ КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage(), 'error', $isCli);
        dumpData($e->getTraceAsString(), 'Stack trace', $isCli);
    }
}

// ============================================
// ЭТАП 4: Проверка всех OTP записей в Cache
// ============================================
if ($action === 'full' || $action === 'check') {
    section("ЭТАП 4: Проверка Cache (все OTP записи)", $isCli);
    
    try {
        // Пытаемся найти все ключи OTP в Cache
        // Примечание: Redis позволяет использовать SCAN, но Laravel Cache не имеет прямого метода
        // Поэтому показываем только известные ключи
        
        if ($otpId) {
            $cacheKey = strtolower($activeGateway) . "_otp_" . $otpId;
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData) {
                output("✅ Найдена запись в Cache:", 'success', $isCli);
                dumpData([
                    'key' => $cacheKey,
                    'data' => $cachedData,
                    'ttl' => '~5 минут'
                ], '', $isCli);
            } else {
                output("ℹ️  Запись не найдена в Cache (возможно, уже удалена или истекла)", 'info', $isCli);
            }
        } else {
            output("ℹ️  Для проверки Cache нужен OTP ID", 'info', $isCli);
        }
        
    } catch (\Exception $e) {
        output("❌ Ошибка проверки Cache: " . $e->getMessage(), 'error', $isCli);
    }
}

// ============================================
// ЭТАП 5: Проверка БД (профили)
// ============================================
if ($action === 'full' || $action === 'check') {
    section("ЭТАП 5: Проверка БД (профили пользователей)", $isCli);
    
    try {
        $profiles = Profile::where('contact', $phone)->get();
        
        if ($profiles->count() > 0) {
            output("✅ Найдено профилей: " . $profiles->count(), 'success', $isCli);
            foreach ($profiles as $profile) {
                dumpData([
                    'id' => $profile->id,
                    'customer_id' => $profile->customer_id,
                    'contact' => $profile->contact,
                    'phone_verified' => $profile->phone_verified ?? 'N/A',
                    'phone_verified_at' => $profile->phone_verified_at ?? 'N/A',
                ], "Профиль #{$profile->id}", $isCli);
            }
        } else {
            output("ℹ️  Профили с таким телефоном не найдены", 'info', $isCli);
        }
        
    } catch (\Exception $e) {
        output("❌ Ошибка проверки БД: " . $e->getMessage(), 'error', $isCli);
    }
}

// ============================================
// ИТОГОВЫЙ ОТЧЕТ
// ============================================
section("ИТОГОВЫЙ ОТЧЕТ", $isCli);

if (empty($errors)) {
    output("✅ Все проверки пройдены успешно!", 'success', $isCli);
} else {
    output("❌ Найдено ошибок: " . count($errors), 'error', $isCli);
    foreach ($errors as $error) {
        output("  • $error", 'error', $isCli);
    }
}

dumpData($results, 'Результаты тестирования', $isCli);

// Инструкции для следующего шага
if ($action === 'full' && isset($results['send']['otp_id']) && isset($results['send']['test_code'])) {
    section("СЛЕДУЮЩИЕ ШАГИ", $isCli);
    output("Для проверки кода используйте:", 'info', $isCli);
    if (!$isCli) {
        $verifyUrl = "?action=verify&phone=" . urlencode($phone) . "&otp_id=" . urlencode($results['send']['otp_id']) . "&code=" . urlencode($results['send']['test_code']);
        output("URL для проверки: <a href='$verifyUrl'>Нажмите здесь</a>", 'info', $isCli);
    } else {
        output("php debug-sms-verification.php \"$phone\" verify \"{$results['send']['otp_id']}\" \"{$results['send']['test_code']}\"", 'info', $isCli);
    }
}

// Конец HTML (если не CLI)
if (!$isCli) {
    echo '</div></body></html>';
}

