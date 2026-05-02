<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Marvel\Database\Models\User;
use Marvel\Database\Models\Profile;
use Marvel\Services\ArticleGeneratorService;
use Illuminate\Support\Facades\DB;

class GenerateSellerIds extends Command
{
    protected $signature = 'users:generate-seller-ids 
                            {--dry-run : Показать что будет сделано без сохранения в БД}
                            {--force : Принудительно перегенерировать для всех пользователей}';
    
    protected $description = 'Генерирует Seller ID для всех пользователей, у которых его еще нет';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('Генерация Seller ID для пользователей...');
        $this->newLine();

        // Находим пользователей без seller_id
        if ($force) {
            $query = Profile::whereNotNull('customer_id');
            $this->warn('Режим --force: будут обновлены ВСЕ профили (включая те, у которых уже есть seller_id)');
        } else {
            $query = Profile::whereNull('seller_id')
                ->orWhere('seller_id', '')
                ->whereNotNull('customer_id');
        }

        $profiles = $query->get();
        $total = $profiles->count();

        if ($total === 0) {
            $this->info('✓ Все пользователи уже имеют Seller ID');
            return 0;
        }

        $this->info("Найдено пользователей без Seller ID: {$total}");
        $this->newLine();

        if ($dryRun) {
            $this->warn('РЕЖИМ DRY-RUN: изменения не будут сохранены');
            $this->newLine();
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($profiles as $profile) {
                try {
                    $user = User::find($profile->customer_id);
                    
                    if (!$user) {
                        $errorCount++;
                        $errors[] = "Профиль ID {$profile->id}: пользователь не найден";
                        $bar->advance();
                        continue;
                    }

                    // Генерируем уникальный seller_id
                    $sellerId = ArticleGeneratorService::generateSellerId();

                    // Проверяем уникальность (на случай если между проверкой и генерацией был создан такой же)
                    while (Profile::where('seller_id', $sellerId)->where('id', '!=', $profile->id)->exists()) {
                        $sellerId = ArticleGeneratorService::generateSellerId();
                    }

                    if ($dryRun) {
                        $this->line("\n  [DRY-RUN] Пользователь: {$user->name} ({$user->email}) → Seller ID: {$sellerId}");
                    } else {
                        $profile->seller_id = $sellerId;
                        $profile->save();
                    }

                    $successCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    $userInfo = $user ? "{$user->name} ({$user->email})" : "ID {$profile->customer_id}";
                    $errors[] = "Пользователь {$userInfo}: {$e->getMessage()}";
                }

                $bar->advance();
            }

            if ($dryRun) {
                DB::rollBack();
                $this->newLine(2);
                $this->info("DRY-RUN завершен. Будет обработано: {$successCount} пользователей");
            } else {
                DB::commit();
                $this->newLine(2);
                $this->info("✓ Успешно обработано: {$successCount} пользователей");
            }

            if ($errorCount > 0) {
                $this->newLine();
                $this->error("✗ Ошибок: {$errorCount}");
                foreach ($errors as $error) {
                    $this->line("  - {$error}");
                }
            }

            $bar->finish();
            $this->newLine(2);

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->newLine(2);
            $this->error("Критическая ошибка: {$e->getMessage()}");
            return 1;
        }
    }
}

