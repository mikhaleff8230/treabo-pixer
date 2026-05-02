#!/usr/bin/env php
<?php

/**
 * Скрипт для тестирования системы импорта и очередей
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Диагностика системы импорта ===\n\n";

// 1. Проверка директорий
echo "1. Проверка директорий для импорта:\n";
$dirs = [
    'xml-import-stats' => storage_path('app/xml-import-stats'),
    'xml-import-progress' => storage_path('app/xml-import-progress'),
    'xml-import-chunks' => storage_path('app/xml-import-chunks'),
];

foreach ($dirs as $name => $path) {
    $exists = is_dir($path);
    $writable = $exists ? is_writable($path) : false;
    echo "  - {$name}: " . ($exists ? '✓ exists' : '✗ missing') . 
         ($writable ? ' (writable)' : ' (NOT writable)') . "\n";
    echo "    Path: {$path}\n";
    
    // Создаем директорию если не существует
    if (!$exists) {
        @mkdir($path, 0777, true);
        echo "    → Created\n";
    }
}

echo "\n2. Проверка очередей:\n";

// Проверяем настройки очереди
$queueDriver = config('queue.default');
echo "  - Queue driver: {$queueDriver}\n";

if ($queueDriver === 'database') {
    // Проверяем таблицу jobs
    try {
        $jobsCount = DB::table('jobs')->count();
        echo "  - Jobs in queue (database): {$jobsCount}\n";
        
        $failedJobsCount = DB::table('failed_jobs')->count();
        echo "  - Failed jobs: {$failedJobsCount}\n";
        
        if ($jobsCount > 0) {
            echo "\n  Latest jobs:\n";
            $latestJobs = DB::table('jobs')->orderBy('id', 'desc')->limit(5)->get();
            foreach ($latestJobs as $job) {
                $payload = json_decode($job->payload, true);
                $command = $payload['displayName'] ?? 'Unknown';
                echo "    - {$command} (attempts: {$job->attempts})\n";
            }
        }
    } catch (\Exception $e) {
        echo "  ✗ Error checking database queue: " . $e->getMessage() . "\n";
    }
} elseif ($queueDriver === 'redis') {
    echo "  - Redis queue (cannot inspect from CLI easily)\n";
} else {
    echo "  - Sync queue (jobs run immediately)\n";
}

echo "\n3. Проверка активных импортов:\n";

// Проверяем файлы прогресса
$progressFiles = glob(storage_path('app/xml-import-progress/*.json'));
echo "  - Active progress files: " . count($progressFiles) . "\n";

if (count($progressFiles) > 0) {
    foreach ($progressFiles as $file) {
        $token = basename($file, '.json');
        $data = json_decode(file_get_contents($file), true);
        echo "    - Token: {$token}\n";
        echo "      Status: {$data['status']}\n";
        echo "      Progress: {$data['progress_percent']}%\n";
    }
}

// Проверяем файлы статистики
$statsFiles = glob(storage_path('app/xml-import-stats/*.json'));
echo "  - Stats files: " . count($statsFiles) . "\n";

if (count($statsFiles) > 0) {
    foreach (array_slice($statsFiles, 0, 5) as $file) {
        $token = basename($file, '.json');
        $data = json_decode(file_get_contents($file), true);
        echo "    - Token: {$token}\n";
        echo "      Total: {$data['total']} | Imported: {$data['imported']} | Updated: {$data['updated']} | Errors: {$data['errors']}\n";
    }
}

echo "\n4. Проверка XmlImportService:\n";
try {
    $service = new \Marvel\Services\XmlImportService();
    echo "  ✓ XmlImportService instantiated successfully\n";
} catch (\Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n5. Проверка ChunkedImportService:\n";
try {
    $service = new \Marvel\Services\ChunkedImportService();
    echo "  ✓ ChunkedImportService instantiated successfully\n";
} catch (\Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Диагностика завершена ===\n";

