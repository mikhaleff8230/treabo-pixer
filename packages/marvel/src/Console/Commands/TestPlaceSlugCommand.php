<?php

namespace Marvel\Console\Commands;

use Illuminate\Console\Command;
use Marvel\Database\Models\Place;
use Marvel\Database\Models\PlaceSlugHistory;
use Marvel\Database\Models\User;
use Illuminate\Support\Facades\DB;

class TestPlaceSlugCommand extends Command
{
    protected $signature = 'test:place-slug {--cleanup : Удалить тестовые данные после теста}';
    protected $description = 'Тестирование SEO-URL для Places с форматом /places/{slug}-{id}';

    private $createdPlaceId = null;

    public function handle()
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  ТЕСТИРОВАНИЕ PLACE SEO-URL');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        try {
            // Этап 1: Создание тестового Place
            $this->test1_createPlace();
            
            // Этап 2: Проверка генерации URL
            $this->test2_checkUrl();
            
            // Этап 3: Обновление slug
            $this->test3_updateSlug();
            
            // Этап 4: Проверка истории slug
            $this->test4_checkSlugHistory();
            
            // Этап 5: Тестирование парсинга URL
            $this->test5_parseUrl();
            
            // Этап 6: Тестирование поиска по старому slug (301 redirect)
            $this->test6_findByOldSlug();

            $this->newLine();
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('  ✅ ВСЕ ТЕСТЫ ПРОЙДЕНЫ УСПЕШНО!');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->newLine();

            // Итоговая статистика
            $this->showSummary();

            // Очистка если указан флаг
            if ($this->option('cleanup')) {
                $this->cleanup();
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ ОШИБКА: ' . $e->getMessage());
            $this->error('Трейс: ' . $e->getTraceAsString());
            
            if ($this->option('cleanup')) {
                $this->cleanup();
            }
            
            return 1;
        }
    }

    private function test1_createPlace()
    {
        $this->info('📋 Этап 1: Создание тестового Place');
        $this->line('─────────────────────────────────────────');

        // Находим первого пользователя
        $user = User::first();
        if (!$user) {
            throw new \Exception('Пользователь не найден. Создайте хотя бы одного пользователя.');
        }

        $place = Place::create([
            'user_id' => $user->id,
            'title' => 'Тестовый Place для SEO URL ' . time(),
            'description' => 'Описание тестового места',
        ]);

        $this->createdPlaceId = $place->id;

        $this->info("✅ Place создан:");
        $this->line("   ID: {$place->id}");
        $this->line("   Title: {$place->title}");
        $this->line("   Slug: {$place->slug}");
        $this->line("   URL: {$place->url}");
        $this->newLine();
    }

    private function test2_checkUrl()
    {
        $this->info('📋 Этап 2: Проверка генерации URL');
        $this->line('─────────────────────────────────────────');

        $place = Place::findOrFail($this->createdPlaceId);
        
        $expectedUrl = "/places/{$place->slug}-{$place->id}";
        
        if ($place->url !== $expectedUrl) {
            throw new \Exception("URL не соответствует ожидаемому. Ожидалось: {$expectedUrl}, Получено: {$place->url}");
        }

        $this->info("✅ URL сгенерирован корректно:");
        $this->line("   {$place->url}");
        $this->newLine();
    }

    private function test3_updateSlug()
    {
        $this->info('📋 Этап 3: Обновление slug');
        $this->line('─────────────────────────────────────────');

        $place = Place::findOrFail($this->createdPlaceId);
        $oldSlug = $place->slug;
        
        $place->title = 'Обновленный Тестовый Place ' . time();
        $place->save();
        
        $place->refresh();
        $newSlug = $place->slug;

        $this->info("✅ Slug обновлен:");
        $this->line("   Старый: {$oldSlug}");
        $this->line("   Новый: {$newSlug}");
        $this->line("   Новый URL: {$place->url}");
        $this->newLine();
    }

    private function test4_checkSlugHistory()
    {
        $this->info('📋 Этап 4: Проверка истории slug');
        $this->line('─────────────────────────────────────────');

        $history = PlaceSlugHistory::where('place_id', $this->createdPlaceId)->get();

        if ($history->isEmpty()) {
            throw new \Exception('История slug пуста. Должна быть хотя бы одна запись.');
        }

        $this->info("✅ История slug ({$history->count()} записей):");
        foreach ($history as $entry) {
            $this->line("   - {$entry->old_slug} (сохранен: {$entry->created_at})");
        }
        $this->newLine();
    }

    private function test5_parseUrl()
    {
        $this->info('📋 Этап 5: Тестирование парсинга URL');
        $this->line('─────────────────────────────────────────');

        $place = Place::findOrFail($this->createdPlaceId);
        
        // Тестируем с текущим slug
        $slugId = "{$place->slug}-{$place->id}";
        $parsed = Place::parseSlugId($slugId);

        if (!$parsed || $parsed['id'] !== $place->id || $parsed['slug'] !== $place->slug) {
            throw new \Exception("Парсинг URL не работает. Входные данные: {$slugId}");
        }

        $this->info("✅ Парсинг URL работает:");
        $this->line("   Входные данные: {$slugId}");
        $this->line("   Распознанный slug: {$parsed['slug']}");
        $this->line("   Распознанный ID: {$parsed['id']}");
        $this->newLine();
    }

    private function test6_findByOldSlug()
    {
        $this->info('📋 Этап 6: Поиск по старому slug (тест редиректа)');
        $this->line('─────────────────────────────────────────');

        $place = Place::findOrFail($this->createdPlaceId);
        $oldSlugEntry = PlaceSlugHistory::where('place_id', $this->createdPlaceId)->first();

        if (!$oldSlugEntry) {
            $this->warn('⚠️  Нет записей в истории slug для тестирования редиректа');
            $this->newLine();
            return;
        }

        $oldSlug = $oldSlugEntry->old_slug;
        
        // Тестируем поиск по старому slug
        $result = Place::findBySlugOrHistory($oldSlug, $place->id);

        if (!$result) {
            throw new \Exception("Не удалось найти Place по старому slug: {$oldSlug}");
        }

        if (!$result['redirect']) {
            throw new \Exception("Должен быть установлен флаг redirect для старого slug");
        }

        if ($result['place']->id !== $place->id) {
            throw new \Exception("Найденный Place имеет неверный ID");
        }

        $this->info("✅ Поиск по старому slug работает:");
        $this->line("   Старый slug: {$oldSlug}");
        $this->line("   Текущий slug: {$place->slug}");
        $this->line("   Флаг redirect: ДА (301 редирект будет выполнен)");
        $this->line("   Редирект на: {$place->url}");
        $this->newLine();
    }

    private function showSummary()
    {
        $place = Place::findOrFail($this->createdPlaceId);
        $historyCount = PlaceSlugHistory::where('place_id', $this->createdPlaceId)->count();

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  ИТОГОВАЯ СТАТИСТИКА');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();
        
        $this->line("📍 Созданный Place:");
        $this->line("   - ID: {$place->id}");
        $this->line("   - Title: {$place->title}");
        $this->line("   - Slug: {$place->slug}");
        $this->line("   - История slug: {$historyCount} записей");
        $this->newLine();
        
        $this->line("🔗 SEO-URL:");
        $this->line("   {$place->url}");
        $this->newLine();
        
        if ($historyCount > 0) {
            $this->line("📜 Старые URL (будут редиректить на текущий):");
            $history = PlaceSlugHistory::where('place_id', $this->createdPlaceId)->get();
            foreach ($history as $entry) {
                $this->line("   /places/{$entry->old_slug}-{$place->id}");
            }
            $this->newLine();
        }

        $this->line("💡 Примеры использования:");
        $this->line("   GET /api/places/{$place->slug}-{$place->id}");
        if ($historyCount > 0) {
            $oldSlug = PlaceSlugHistory::where('place_id', $this->createdPlaceId)->first()->old_slug;
            $this->line("   GET /api/places/{$oldSlug}-{$place->id} → 301 редирект");
        }
        $this->line("   GET /api/places/{$place->id} → 301 редирект (старый формат)");
        $this->newLine();
    }

    private function cleanup()
    {
        $this->newLine();
        $this->warn('🧹 Очистка тестовых данных...');

        if ($this->createdPlaceId) {
            // Удаляем историю slug
            PlaceSlugHistory::where('place_id', $this->createdPlaceId)->delete();
            
            // Удаляем Place
            Place::where('id', $this->createdPlaceId)->delete();
            
            $this->info("✅ Тестовый Place (ID: {$this->createdPlaceId}) удален");
        }

        $this->newLine();
    }
}

