<?php
// Тест Laravel сервера
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo "=== ТЕСТ LARAVEL СЕРВЕРА ===\n";

// Проверяем, что Laravel сервер запущен
$serverUrl = 'http://localhost:8000';
$testEndpoints = [
    '/places',
    '/api/places',
    '/api/v1/places'
];

echo "Проверяем Laravel сервер на $serverUrl\n";

foreach ($testEndpoints as $endpoint) {
    $url = $serverUrl . $endpoint;
    echo "\nТестируем: $url\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ Ошибка CURL: $error\n";
    } else {
        echo "✅ HTTP код: $httpCode\n";
        
        if ($httpCode == 200) {
            echo "✅ Сервер отвечает!\n";
            // Показываем первые 500 символов ответа
            $body = substr($response, strpos($response, "\r\n\r\n") + 4);
            echo "Ответ: " . substr($body, 0, 500) . "...\n";
        } else {
            echo "❌ Сервер не отвечает корректно\n";
        }
    }
}

// Проверяем через file_get_contents
echo "\nПроверяем через file_get_contents:\n";
$context = stream_context_create([
    'http' => [
        'timeout' => 5,
        'header' => "Accept: application/json\r\n"
    ]
]);

foreach ($testEndpoints as $endpoint) {
    $url = $serverUrl . $endpoint;
    echo "\nТестируем: $url\n";
    
    $result = @file_get_contents($url, false, $context);
    if ($result === false) {
        echo "❌ Не удалось получить ответ\n";
    } else {
        echo "✅ Получен ответ длиной: " . strlen($result) . " символов\n";
        echo "Ответ: " . substr($result, 0, 200) . "...\n";
    }
}

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";
?> 