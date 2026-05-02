#!/usr/bin/env php
<?php

/**
 * Скрипт для запуска теста галереи
 * Использование: php tests/run-gallery-test.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Tests\GalleryTestScript;

echo "Запуск теста галереи товаров...\n\n";

try {
    $test = new GalleryTestScript();
    $test->run();
} catch (Exception $e) {
    echo "ОШИБКА: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\nТест завершен.\n";

