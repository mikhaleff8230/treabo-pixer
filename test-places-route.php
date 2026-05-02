<?php

// Простой тест для проверки маршрута плейсов
$baseUrl = 'http://localhost:8000'; // или ваш домен

echo "Тестирование маршрутов плейсов...\n\n";

// Тест 1: Получение списка плейсов (публичный маршрут)
echo "1. Тест GET /api/places (публичный):\n";
$url = $baseUrl . '/api/places';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "URL: $url\n";
echo "HTTP Code: $httpCode\n";
echo "Response: " . substr($response, 0, 500) . "...\n\n";

// Тест 2: Создание плейса (требует авторизацию)
echo "2. Тест POST /api/places (требует авторизацию):\n";
$url = $baseUrl . '/api/places';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'title' => 'Тестовый плейс',
    'description' => 'Тестовое описание'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "URL: $url\n";
echo "HTTP Code: $httpCode\n";
echo "Response: " . substr($response, 0, 500) . "...\n\n";

// Тест 3: Поиск товаров
echo "3. Тест GET /api/places/search/products:\n";
$url = $baseUrl . '/api/places/search/products?q=тест&limit=5';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "URL: $url\n";
echo "HTTP Code: $httpCode\n";
echo "Response: " . substr($response, 0, 500) . "...\n\n";

echo "Тестирование завершено.\n"; 