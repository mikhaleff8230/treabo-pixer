<?php

/**
 * Финальный тест импорта плейсов после исправлений
 * Запуск: php test-place-import-final.php
 */

require_once __DIR__ . '/vendor/autoload.php';

// Инициализация Laravel приложения
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Marvel\Database\Models\User;
use Marvel\Database\Models\Place;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

echo "=== ФИНАЛЬНЫЙ ТЕСТ ИМПОРТА ПЛЕЙСОВ ===\n\n";

// 1. Проверка структуры таблицы places
echo "1. Проверка структуры таблицы places:\n";
try {
    $columns = DB::select("SHOW COLUMNS FROM places");
    $columnNames = array_column($columns, 'Field');

    echo "   ✓ Колонки в таблице places: " . implode(', ', $columnNames) . "\n";

    $requiredColumns = ['id', 'user_id', 'title', 'description', 'source_url', 'slug', 'language'];
    $missingColumns = array_diff($requiredColumns, $columnNames);

    if (!empty($missingColumns)) {
        echo "   ❌ Отсутствуют колонки: " . implode(', ', $missingColumns) . "\n";
        echo "   → Выполните: php artisan migrate\n";
        exit(1);
    } else {
        echo "   ✓ Все необходимые колонки присутствуют\n";
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка проверки структуры: {$e->getMessage()}\n";
    exit(1);
}

// 2. Проверка пользователей с правами доступа
echo "\n2. Проверка пользователей с правами:\n";
try {
    $users = User::where('is_active', true)->limit(5)->get();

    foreach ($users as $user) {
        echo "   ✓ Пользователь ID {$user->id}: {$user->name} ({$user->email})\n";

        $hasAccess = false;
        try {
            if ($user->hasRole(['store_owner', 'staff', 'super_admin'])) {
                $hasAccess = true;
                echo "     ✓ Имеет необходимые права доступа\n";
                $testUser = $user;
                break;
            }
        } catch (Exception $e) {
            // Альтернативная проверка через permissions
        }

        if (!$hasAccess) {
            echo "     ✗ Не имеет прав доступа\n";
        }
    }

    if (!isset($testUser)) {
        echo "   ⚠️ Нет пользователей с правами доступа к place-parser\n";
        echo "   → Нужно дать кому-то роль store_owner или staff\n";
        $testUser = $users->first();
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка проверки пользователей: {$e->getMessage()}\n";
    exit(1);
}

// 3. Тест создания плейса со всеми полями
echo "\n3. Тест создания плейса со всеми полями:\n";
try {
    $placeData = [
        'user_id' => $testUser->id,
        'title' => 'Финальный тестовый плейс ' . time(),
        'description' => 'Полное описание для финального теста',
        'source_url' => 'https://example.com/final-test', // Теперь должно работать!
        'language' => 'ru',
    ];

    $place = Place::create($placeData);
    echo "   ✓ Плейс создан успешно! ID: {$place->id}\n";
    echo "   ✓ Title: {$place->title}\n";
    echo "   ✓ Source URL: {$place->source_url}\n";
    echo "   ✓ Slug: {$place->slug}\n";

    // Проверяем, что плейс создался в БД
    $checkPlace = Place::find($place->id);
    if ($checkPlace && $checkPlace->source_url === $placeData['source_url']) {
        echo "   ✓ Плейс корректно сохранен в БД\n";
    } else {
        echo "   ❌ Плейс не найден или source_url не сохранен\n";
    }

} catch (Exception $e) {
    echo "   ❌ Ошибка создания плейса: {$e->getMessage()}\n";
    echo "   Stack trace: {$e->getTraceAsString()}\n";
}

// 4. Тест имитации bulk создания (как в контроллере)
echo "\n4. Тест bulk создания плейсов:\n";
try {
    $bulkPlaces = [
        [
            'title' => 'Bulk плейс 1 ' . time(),
            'description' => 'Описание bulk 1',
            'source_url' => 'https://bulk1.com', // Теперь должно работать!
            'hashtags' => ['bulk', 'test1'],
        ],
        [
            'title' => 'Bulk плейс 2 ' . time(),
            'description' => 'Описание bulk 2',
            'source_url' => 'https://bulk2.com', // Теперь должно работать!
            'hashtags' => ['bulk', 'test2'],
        ],
    ];

    $created = 0;
    $errors = [];

    foreach ($bulkPlaces as $index => $placeData) {
        try {
            $place = Place::create([
                'user_id' => $testUser->id,
                'title' => $placeData['title'],
                'description' => $placeData['description'] ?? null,
                'source_url' => $placeData['source_url'] ?? null, // Теперь должно работать!
                'language' => 'ru',
            ]);

            // Добавляем хештеги
            if (!empty($placeData['hashtags'])) {
                $hashtagIds = [];
                foreach ($placeData['hashtags'] as $tag) {
                    $tagName = trim(ltrim($tag, '#'));
                    if (!empty($tagName)) {
                        $hashtag = \Marvel\Database\Models\Hashtag::firstOrCreate(
                            ['name' => $tagName],
                            ['slug' => \Illuminate\Support\Str::slug($tagName)]
                        );
                        $hashtagIds[] = $hashtag->id;
                    }
                }
                $place->hashtags()->sync($hashtagIds);
            }

            $created++;
            echo "   ✓ Создан bulk плейс {$index}: {$place->title}\n";

        } catch (Exception $e) {
            $errors[] = [
                'index' => $index,
                'title' => $placeData['title'] ?? 'Unknown',
                'error' => $e->getMessage(),
            ];
            echo "   ❌ Ошибка создания bulk плейса {$index}: {$e->getMessage()}\n";
        }
    }

    echo "   ✓ Создано bulk плейсов: {$created}\n";

    if (!empty($errors)) {
        echo "   ⚠️ Ошибки в bulk создании:\n";
        foreach ($errors as $error) {
            echo "     - {$error['title']}: {$error['error']}\n";
        }
    }

} catch (Exception $e) {
    echo "   ❌ Ошибка bulk теста: {$e->getMessage()}\n";
}

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";
echo "Если все тесты прошли успешно, импорт плейсов должен работать!\n";
