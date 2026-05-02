<?php

/**
 * Отладка CSV импорта
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ОТЛАДКА CSV ИМПОРТА ===\n\n";

// Укажите путь к вашему CSV файлу
$csvPath = $argv[1] ?? null;

if (!$csvPath || !file_exists($csvPath)) {
    echo "Использование: php debug-csv-import.php /path/to/file.csv\n";
    echo "Или укажите URL:\n";
    echo "php debug-csv-import.php \"https://example.com/products.csv\"\n";
    exit(1);
}

echo "Файл: {$csvPath}\n\n";

// Читаем файл
if (strpos($csvPath, 'http') === 0) {
    $content = file_get_contents($csvPath);
} else {
    $content = file_get_contents($csvPath);
}

// Парсим CSV
$lines = preg_split("/(\r\n|\n|\r)/", trim($content));
echo "Всего строк: " . count($lines) . "\n\n";

if (empty($lines)) {
    echo "❌ Файл пустой\n";
    exit(1);
}

// Заголовки
$headers = str_getcsv(array_shift($lines));
echo "Заголовки CSV (" . count($headers) . " колонок):\n";
foreach ($headers as $idx => $header) {
    echo "  [{$idx}] \"{$header}\"\n";
}
echo "\n";

// Первые 3 строки данных
echo "Первые 3 строки данных:\n";
echo str_repeat("=", 70) . "\n";

for ($i = 0; $i < min(3, count($lines)); $i++) {
    if (empty($lines[$i])) continue;
    
    $values = str_getcsv($lines[$i]);
    echo "\nСтрока " . ($i + 2) . ":\n";
    
    if (count($values) !== count($headers)) {
        $values = array_pad($values, count($headers), null);
    }
    
    $row = array_combine($headers, $values);
    foreach ($row as $key => $value) {
        $displayValue = is_null($value) ? 'NULL' : (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value);
        echo "  {$key}: {$displayValue}\n";
    }
}

echo "\n" . str_repeat("=", 70) . "\n\n";

// Проверяем наличие обязательных полей
$requiredFields = ['name', 'sku'];
$missingFields = [];

echo "Проверка обязательных полей:\n";
foreach ($requiredFields as $field) {
    $found = false;
    foreach ($headers as $header) {
        if (strcasecmp($header, $field) === 0 || 
            stripos($header, $field) !== false) {
            $found = true;
            echo "✅ '{$field}' найдено как '{$header}'\n";
            break;
        }
    }
    if (!$found) {
        $missingFields[] = $field;
        echo "❌ '{$field}' НЕ НАЙДЕНО\n";
    }
}

if (!empty($missingFields)) {
    echo "\n⚠️  ВНИМАНИЕ: Отсутствуют обязательные поля: " . implode(', ', $missingFields) . "\n";
    echo "\nРекомендуемый маппинг для этого CSV:\n";
    echo "{\n";
    foreach ($headers as $header) {
        $cleanHeader = trim($header);
        // Угадываем поле
        $guessedField = null;
        if (stripos($cleanHeader, 'name') !== false || stripos($cleanHeader, 'название') !== false || stripos($cleanHeader, 'наименование') !== false) {
            $guessedField = 'name';
        } elseif (stripos($cleanHeader, 'sku') !== false || stripos($cleanHeader, 'артикул') !== false || stripos($cleanHeader, 'код') !== false) {
            $guessedField = 'sku';
        } elseif (stripos($cleanHeader, 'price') !== false || stripos($cleanHeader, 'цена') !== false) {
            $guessedField = 'price';
        } elseif (stripos($cleanHeader, 'description') !== false || stripos($cleanHeader, 'описание') !== false) {
            $guessedField = 'description';
        } elseif (stripos($cleanHeader, 'image') !== false || stripos($cleanHeader, 'изображ') !== false || stripos($cleanHeader, 'фото') !== false || stripos($cleanHeader, 'картинка') !== false) {
            $guessedField = 'image';
        } elseif (stripos($cleanHeader, 'category') !== false || stripos($cleanHeader, 'категория') !== false) {
            $guessedField = 'category';
        } elseif (stripos($cleanHeader, 'quantity') !== false || stripos($cleanHeader, 'количество') !== false || stripos($cleanHeader, 'остаток') !== false) {
            $guessedField = 'quantity';
        }
        
        if ($guessedField) {
            echo "  \"{$guessedField}\": \"{$cleanHeader}\",\n";
        }
    }
    echo "}\n";
} else {
    echo "\n✅ Все обязательные поля присутствуют\n";
}

echo "\n=== ОТЛАДКА ЗАВЕРШЕНА ===\n";

