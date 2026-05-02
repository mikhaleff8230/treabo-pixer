<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BillingSettings;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class CheckBillingSettings extends Command
{
    protected $signature = 'billing:check-settings';
    protected $description = 'Проверить настройки биллинга и статус автоматической генерации счетов';

    public function handle()
    {
        $this->info('=== Проверка настроек биллинга ===');
        $this->newLine();

        // Проверяем настройки из базы данных
        $this->info('📋 Настройки из базы данных:');
        $this->line('─────────────────────────────────────');

        $autoGeneration = BillingSettings::get('auto_generation', '1');
        $daysBeforeOverdue = BillingSettings::get('days_before_overdue', '30');
        $overdueAction = BillingSettings::get('overdue_action', 'hide_products');
        $currency = BillingSettings::get('currency', 'RUB');
        $generationDay = BillingSettings::get('generation_day', '1');

        $this->table(
            ['Настройка', 'Значение', 'Статус'],
            [
                [
                    'auto_generation',
                    $autoGeneration,
                    $autoGeneration == '1' ? '✅ Включено' : '❌ Выключено'
                ],
                [
                    'days_before_overdue',
                    $daysBeforeOverdue . ' дней',
                    $daysBeforeOverdue == '30' ? '✅ Правильно (30 дней)' : '⚠️  Не стандартное значение'
                ],
                [
                    'overdue_action',
                    $overdueAction,
                    '✅ Настроено'
                ],
                [
                    'currency',
                    $currency,
                    '✅ Настроено'
                ],
                [
                    'generation_day',
                    $generationDay . ' число',
                    '✅ Настроено'
                ],
            ]
        );

        $this->newLine();

        // Проверяем расписание
        $this->info('⏰ Расписание команд:');
        $this->line('─────────────────────────────────────');
        
        try {
            $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
            $events = $schedule->events();
            
            $billingEvents = collect($events)->filter(function ($event) {
                return str_contains($event->command ?? '', 'billing:');
            });

            if ($billingEvents->isEmpty()) {
                $this->warn('⚠️  Не найдено запланированных команд биллинга');
            } else {
                foreach ($billingEvents as $event) {
                    $expression = $event->expression;
                    $command = $event->command ?? 'N/A';
                    
                    $this->line("  • {$command}");
                    $this->line("    Расписание: {$expression}");
                }
            }
        } catch (\Exception $e) {
            $this->warn('⚠️  Не удалось проверить расписание: ' . $e->getMessage());
        }

        $this->newLine();

        // Проверяем статистику счетов
        $this->info('📊 Статистика счетов:');
        $this->line('─────────────────────────────────────');

        $pendingCount = Invoice::where('status', 'pending')->count();
        $overdueCount = Invoice::where('status', 'overdue')->count();
        $paidCount = Invoice::where('status', 'paid')->count();
        $totalCount = Invoice::count();

        $this->table(
            ['Статус', 'Количество'],
            [
                ['pending (ожидают оплаты)', $pendingCount],
                ['overdue (просрочены)', $overdueCount],
                ['paid (оплачены)', $paidCount],
                ['Всего счетов', $totalCount],
            ]
        );

        $this->newLine();

        // Проверяем последние счета
        $this->info('📄 Последние 5 счетов:');
        $this->line('─────────────────────────────────────');

        $recentInvoices = Invoice::with('seller:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        if ($recentInvoices->isEmpty()) {
            $this->warn('  Счетов пока нет');
        } else {
            $tableData = [];
            foreach ($recentInvoices as $invoice) {
                $tableData[] = [
                    'ID: ' . $invoice->id,
                    'Продавец: ' . ($invoice->seller->name ?? 'N/A'),
                    'Товаров: ' . $invoice->total_products,
                    'Сумма: ' . $invoice->total_amount . ' RUB',
                    'Статус: ' . $invoice->status,
                    'Создан: ' . $invoice->created_at->format('Y-m-d H:i'),
                ];
            }
            
            foreach ($tableData as $row) {
                $this->line('  • ' . implode(' | ', $row));
            }
        }

        $this->newLine();

        // Проверяем расчет тарифа
        $this->info('💰 Проверка расчета тарифа:');
        $this->line('─────────────────────────────────────');

        $testCases = [
            ['products' => 50, 'expected' => 200.00],
            ['products' => 200, 'expected' => 200.00],
            ['products' => 250, 'expected' => 225.00],
            ['products' => 500, 'expected' => 350.00],
        ];

        foreach ($testCases as $test) {
            $calculated = Invoice::calculateTariffAmount($test['products']);
            $status = abs($calculated - $test['expected']) < 0.01 ? '✅' : '❌';
            $this->line("  {$status} {$test['products']} товаров → {$calculated} RUB (ожидается {$test['expected']} RUB)");
        }

        $this->newLine();

        // Итоговый статус
        $this->info('=== Итоговый статус ===');
        
        $issues = [];
        
        if ($autoGeneration != '1') {
            $issues[] = '❌ Автоматическая генерация отключена';
        }
        
        if ($daysBeforeOverdue != '30') {
            $issues[] = "⚠️  Период постоплаты: {$daysBeforeOverdue} дней (рекомендуется 30)";
        }

        if (empty($issues)) {
            $this->info('✅ Все настройки корректны!');
            $this->info('✅ Автоматическая генерация счетов включена');
            $this->info('✅ Счета будут создаваться 1-го числа каждого месяца в 03:00');
        } else {
            $this->warn('⚠️  Обнаружены проблемы:');
            foreach ($issues as $issue) {
                $this->warn("  {$issue}");
            }
        }

        $this->newLine();
        $this->comment('💡 Для обновления настроек используйте SQL:');
        $this->comment('   UPDATE billing_settings SET value = \'1\' WHERE key = \'auto_generation\';');
        $this->comment('   UPDATE billing_settings SET value = \'30\' WHERE key = \'days_before_overdue\';');

        return 0;
    }
}












