<?php

// Простой тест API плейсов
$apiUrl = 'https://sancan.ru/api/places';

echo "=== Тест API плейсов ===\n";
echo "URL: {$apiUrl}\n\n";

$response = file_get_contents($apiUrl);
if ($response === false) {
    echo "Ошибка: не удалось получить данные\n";
    exit;
}

$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "Ошибка JSON: " . json_last_error_msg() . "\n";
    echo "Ответ: " . substr($response, 0, 500) . "\n";
    exit;
}

echo "Успешно получены данные!\n";
echo "Количество плейсов: " . count($data['data']) . "\n\n";

foreach ($data['data'] as $place) {
    echo "Place ID: {$place['id']} | Title: {$place['title']}\n";
    echo "Изображений: " . count($place['images']) . "\n";
    echo "Видео: " . count($place['videos']) . "\n";
    
    if (count($place['videos']) > 0) {
        foreach ($place['videos'] as $video) {
            echo "  - Video URL: {$video}\n";
        }
    }
    echo "---\n";
}

echo "\n=== Тест завершен ===\n"; 