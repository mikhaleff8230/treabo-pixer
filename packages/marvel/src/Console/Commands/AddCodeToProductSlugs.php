<?php

namespace Marvel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\ProductSlugHistory;

class AddCodeToProductSlugs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:add-code-to-slugs 
                            {--dry-run : Выполнить без сохранения в БД}
                            {--force : Обновить даже товары с кодом}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Добавляет 12-значный код к slug всех товаров, у которых его нет';

    protected $dryRun = false;
    protected $force = false;
    protected $updated = 0;
    protected $skipped = 0;
    protected $errors = 0;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->dryRun = $this->option('dry-run');
        $this->force = $this->option('force');

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('Добавление 12-значного кода к slug товаров');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        if ($this->dryRun) {
            $this->warn('🔍 Режим DRY-RUN: изменения НЕ будут сохранены в БД');
            $this->newLine();
        }

        if ($this->force) {
            $this->warn('⚠️  Режим FORCE: будут обновлены ВСЕ товары (даже с кодом)');
            $this->newLine();
        }

        try {
            DB::beginTransaction();

            // Получаем все товары
            $query = Product::query();
            
            if (!$this->force) {
                // Только товары без 12-значного кода в конце slug
                // Паттерн: НЕ заканчивается на -123456789012 (ровно 12 цифр)
                $query->where(function($q) {
                    $q->where('slug', 'NOT REGEXP', '-[0-9]{12}$')
                      ->orWhereNull('slug');
                });
            }

            $totalProducts = $query->count();
            
            if ($totalProducts === 0) {
                $this->info('✅ Все товары уже имеют 12-значный код в slug');
                return Command::SUCCESS;
            }

            $this->info("📦 Найдено товаров для обработки: {$totalProducts}");
            $this->newLine();

            $bar = $this->output->createProgressBar($totalProducts);
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');

            $products = $query->get();

            foreach ($products as $product) {
                $bar->setMessage("Обработка: {$product->name}");
                
                try {
                    $this->processProduct($product);
                    $bar->advance();
                } catch (\Exception $e) {
                    $this->errors++;
                    Log::error('AddCodeToProductSlugs - Error processing product', [
                        'product_id' => $product->id,
                        'product_slug' => $product->slug,
                        'error' => $e->getMessage(),
                    ]);
                    $bar->advance();
                }
            }

            $bar->finish();
            $this->newLine(2);

            if (!$this->dryRun) {
                DB::commit();
                $this->info('✅ Изменения сохранены в БД');
            } else {
                DB::rollBack();
                $this->info('🔍 Изменения НЕ сохранены (dry-run режим)');
            }

            $this->newLine();
            $this->displaySummary();

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Ошибка выполнения команды: ' . $e->getMessage());
            Log::error('AddCodeToProductSlugs - Fatal error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Обработка одного товара
     */
    protected function processProduct(Product $product)
    {
        $oldSlug = $product->slug;

        // Проверяем, нужно ли обновлять slug
        if (!$this->force && $this->hasValidCode($oldSlug)) {
            $this->skipped++;
            return;
        }

        // Удаляем старый код (если есть буквенно-цифровой)
        $baseSlug = $this->removeOldCode($oldSlug);

        // Генерируем новый slug с 12-значным кодом
        $newSlug = $this->generateNewSlug($baseSlug, $product->language);

        if (!$newSlug || $newSlug === $oldSlug) {
            $this->skipped++;
            return;
        }

        // Сохраняем старый slug в историю
        if (!empty($oldSlug) && $oldSlug !== $newSlug) {
            $this->saveSlugHistory($product, $oldSlug);
        }

        // Обновляем slug
        if (!$this->dryRun) {
            $product->slug = $newSlug;
            $product->save();
        }

        $this->updated++;

        Log::info('AddCodeToProductSlugs - Product slug updated', [
            'product_id' => $product->id,
            'old_slug' => $oldSlug,
            'new_slug' => $newSlug,
            'dry_run' => $this->dryRun,
        ]);
    }

    /**
     * Проверяет, имеет ли slug валидный 12-значный код
     */
    protected function hasValidCode(string $slug): bool
    {
        // Проверяем, заканчивается ли slug на -123456789012 (ровно 12 цифр)
        return preg_match('/-\d{12}$/', $slug) === 1;
    }

    /**
     * Удаляет старый код из slug (буквенно-цифровой или короткий числовой)
     */
    protected function removeOldCode(string $slug): string
    {
        // Удаляем старый буквенно-цифровой код (например: -SE5, -XA2B4C)
        $slug = preg_replace('/-[A-Z0-9]{3,6}$/', '', $slug);
        
        // Удаляем старый ID (1-10 цифр, но не размеры/годы)
        // НЕ удаляем: 120, 90, 2024 (это размеры/годы)
        $slug = preg_replace('/-(\d{11,}|\d{5,10}|[1-9]\d{0,1})$/', '', $slug);
        
        return $slug;
    }

    /**
     * Генерирует новый slug с 12-значным кодом
     */
    protected function generateNewSlug(string $baseSlug, string $language): string
    {
        $maxAttempts = 10;
        $attempt = 0;

        do {
            // Генерируем 12 случайных цифр
            $code = '';
            for ($i = 0; $i < 12; $i++) {
                $code .= random_int(0, 9);
            }

            $newSlug = "{$baseSlug}-{$code}";

            // Проверяем уникальность
            $exists = Product::where('slug', $newSlug)
                ->where('language', $language)
                ->exists();

            $attempt++;

            if (!$exists) {
                return $newSlug;
            }

        } while ($attempt < $maxAttempts);

        // Если не смогли сгенерировать уникальный - используем timestamp
        $timestamp = time();
        return "{$baseSlug}-{$timestamp}000000";
    }

    /**
     * Сохраняет старый slug в историю
     */
    protected function saveSlugHistory(Product $product, string $oldSlug)
    {
        if ($this->dryRun) {
            return;
        }

        try {
            ProductSlugHistory::firstOrCreate([
                'product_id' => $product->id,
                'old_slug' => $oldSlug,
                'language' => $product->language ?? 'ru',
            ], [
                'changed_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('AddCodeToProductSlugs - Failed to save slug history', [
                'product_id' => $product->id,
                'old_slug' => $oldSlug,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Выводит итоговую статистику
     */
    protected function displaySummary()
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 СТАТИСТИКА:');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->table(
            ['Статус', 'Количество'],
            [
                ['✅ Обновлено', $this->updated],
                ['⏭️  Пропущено', $this->skipped],
                ['❌ Ошибок', $this->errors],
                ['📦 Всего обработано', $this->updated + $this->skipped],
            ]
        );
    }
}

