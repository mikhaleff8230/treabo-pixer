<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class RecalculateOldInvoices extends Command
{
    protected $signature = 'billing:recalculate-old-invoices 
                            {--status= : Статус счетов для обновления (pending, overdue, all)}
                            {--dry-run : Показать что будет обновлено без изменений}';
    
    protected $description = 'Пересчитать старые счета по новому тарифу (200 руб за первые 200 товаров, 0.5 руб за каждый последующий)';

    public function handle()
    {
        $status = $this->option('status') ?? 'pending';
        $dryRun = $this->option('dry-run');

        $this->info('Пересчет старых счетов по новому тарифу...');
        $this->newLine();

        // Определяем какие счета обновлять
        $query = Invoice::query();
        
        if ($status === 'all') {
            // Обновляем все счета кроме оплаченных
            $query->whereIn('status', ['pending', 'overdue']);
        } else {
            $query->where('status', $status);
        }

        $invoices = $query->get();
        
        if ($invoices->isEmpty()) {
            $this->warn('Не найдено счетов для обновления.');
            return 0;
        }

        $this->info("Найдено счетов для обновления: {$invoices->count()}");
        $this->newLine();

        $updated = 0;
        $skipped = 0;
        $totalDiff = 0;

        foreach ($invoices as $invoice) {
            $oldAmount = (float) $invoice->total_amount;
            $newAmount = Invoice::calculateTariffAmount($invoice->total_products);
            $diff = $newAmount - $oldAmount;
            
            // Показываем информацию о счете
            $this->line("Счет #{$invoice->id} (Продавец: {$invoice->seller_id}):");
            $this->line("  Товаров: {$invoice->total_products}");
            $this->line("  Старая сумма: {$oldAmount} RUB");
            $this->line("  Новая сумма: {$newAmount} RUB");
            
            if ($diff != 0) {
                $this->line("  Разница: " . ($diff > 0 ? '+' : '') . "{$diff} RUB");
                $totalDiff += $diff;
            } else {
                $this->line("  Разница: 0 RUB (без изменений)");
            }

            if (!$dryRun) {
                // Пересчитываем среднюю цену за товар
                $averagePricePerProduct = $invoice->total_products > 0 
                    ? $newAmount / $invoice->total_products 
                    : 0;

                $invoice->update([
                    'total_amount' => $newAmount,
                    'price_per_product' => $averagePricePerProduct,
                ]);

                $updated++;
                $this->info("  ✅ Обновлен");
            } else {
                $this->comment("  ⏸ Пропущен (dry-run режим)");
                $skipped++;
            }
            
            $this->newLine();
        }

        $this->newLine();
        $this->info("=== Итоги ===");
        $this->info("Обновлено счетов: {$updated}");
        
        if ($dryRun) {
            $this->comment("Пропущено (dry-run): {$skipped}");
        }
        
        if ($totalDiff != 0) {
            $this->info("Общая разница: " . ($totalDiff > 0 ? '+' : '') . "{$totalDiff} RUB");
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment("Запустите команду без --dry-run для применения изменений:");
            $this->comment("php artisan billing:recalculate-old-invoices --status={$status}");
        }

        return 0;
    }
}












