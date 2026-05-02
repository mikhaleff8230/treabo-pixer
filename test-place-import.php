<?php

/**
 * Тестовый скрипт для проверки импорта плейсов
 * Запуск: php test-place-import.php
 */

require_once __DIR__ . '/vendor/autoload.php';

// Инициализация Laravel приложения
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Marvel\Http\Controllers\PlaceParserController;
use Marvel\Database\Models\User;
use Marvel\Database\Models\Place;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

echo "=== ТЕСТ ИМПОРТА ПЛЕЙСОВ ===\n\n";

// 1. Проверим, есть ли пользователи в БД
echo "1. Проверка пользователей:\n";
$users = User::where('is_active', true)->limit(5)->get();
if ($users->isEmpty()) {
    echo "   ❌ Нет активных пользователей!\n";
    exit(1);
}

foreach ($users as $user) {
    echo "   ✓ Пользователь ID {$user->id}: {$user->name} ({$user->email})\n";
    try {
        if ($user->hasRole('super_admin')) {
            echo "     ✓ Имеет роль super_admin\n";
            $testUser = $user;
            break;
        } else {
            echo "     ✗ Не имеет роль super_admin\n";
        }
    } catch (Exception $e) {
        echo "     ⚠️ Ошибка проверки роли: {$e->getMessage()}\n";
        // Попробуем альтернативный способ проверки
        $roles = $user->roles()->where('name', 'super_admin')->exists();
        if ($roles) {
            echo "     ✓ Имеет роль super_admin (альтернативная проверка)\n";
            $testUser = $user;
            break;
        }
    }
}

if (!isset($testUser)) {
    echo "   ⚠️ Нет пользователя с ролью super_admin!\n";
    echo "   → Используем первого активного пользователя для тестирования\n";
    $testUser = $users->first();
    if (!$testUser) {
        echo "   ❌ Нет активных пользователей!\n";
        exit(1);
    }
}

// 2. Проверим модель Place
echo "\n2. Проверка модели Place:\n";
try {
    $fillable = (new Place())->getFillable();
    echo "   ✓ Fillable поля: " . implode(', ', $fillable) . "\n";
} catch (Exception $e) {
    echo "   ❌ Ошибка модели Place: {$e->getMessage()}\n";
}

// 3. Проверим таблицу places
echo "\n3. Проверка таблицы places:\n";
try {
    $count = DB::table('places')->count();
    echo "   ✓ В таблице places {$count} записей\n";

    $latest = DB::table('places')->latest('id')->first();
    if ($latest) {
        echo "   ✓ Последний плейс ID {$latest->id}: {$latest->title}\n";
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка доступа к таблице places: {$e->getMessage()}\n";
}

// 4. Тест создания плейса напрямую
echo "\n4. Тест создания плейса:\n";
try {
            $placeData = [
        'user_id' => $testUser->id,
        'title' => 'Тестовый плейс ' . time(),
                'description' => 'Тестовое описание',
                // Пока без source_url - сначала нужно выполнить миграцию
            ];

    $place = Place::create($placeData);
    echo "   ✓ Плейс создан успешно! ID: {$place->id}, Title: {$place->title}\n";

    // Проверим, что плейс действительно создался
    $checkPlace = Place::find($place->id);
    if ($checkPlace) {
        echo "   ✓ Плейс найден в БД: {$checkPlace->title}\n";
    } else {
        echo "   ❌ Плейс не найден в БД!\n";
    }

} catch (Exception $e) {
    echo "   ❌ Ошибка создания плейса: {$e->getMessage()}\n";
    echo "   Stack trace: {$e->getTraceAsString()}\n";
}

// 5. Тест создания плейсов напрямую (без контроллера)
echo "\n5. Тест создания плейсов напрямую:\n";
try {
    $testPlaces = [
        [
            'title' => 'Тестовый плейс bulk 1 ' . time(),
            'description' => 'Описание 1',
            // 'source_url' => 'https://test1.com', // Пока без source_url
            'hashtags' => ['test1', 'bulk'],
            'image' => null,
        ],
        [
            'title' => 'Тестовый плейс bulk 2 ' . time(),
            'description' => 'Описание 2',
            // 'source_url' => 'https://test2.com', // Пока без source_url
            'hashtags' => ['test2', 'bulk'],
            'image' => null,
        ],
    ];

    $created = 0;
    $errors = [];

    foreach ($testPlaces as $index => $placeData) {
        try {
            // Создаем плейс
            $place = Place::create([
                'user_id' => $testUser->id,
                'title' => $placeData['title'],
                'description' => $placeData['description'] ?? null,
                // 'source_url' => $placeData['source_url'] ?? null, // Пока без source_url
            ]);

            // Обрабатываем хештеги
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
            echo "   ✓ Создан плейс ID {$place->id}: {$place->title}\n";

        } catch (Exception $e) {
            $errors[] = [
                'index' => $index,
                'title' => $placeData['title'] ?? 'Unknown',
                'error' => $e->getMessage(),
            ];
            echo "   ❌ Ошибка создания плейса {$index}: {$e->getMessage()}\n";
        }
    }

    echo "   ✓ Всего создано плейсов: {$created}\n";

    if (!empty($errors)) {
        echo "   ⚠️ Ошибки:\n";
        foreach ($errors as $error) {
            echo "     - {$error['title']}: {$error['error']}\n";
        }
    }

} catch (Exception $e) {
    echo "   ❌ Ошибка в тесте создания: {$e->getMessage()}\n";
    echo "   Stack trace: {$e->getTraceAsString()}\n";
}

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";
