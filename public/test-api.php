<?php
// Тестовый файл для проверки PHP-FPM
header('Content-Type: application/json');

echo json_encode([
    'status' => 'success',
    'message' => 'PHP-FPM работает!',
    'php_version' => PHP_VERSION,
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'time' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI']
]);
?> 