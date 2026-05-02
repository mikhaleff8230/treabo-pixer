<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BillingSettings;
use Illuminate\Support\Facades\DB;

class UpdateBillingPostpayment extends Command
{
    protected $signature = 'billing:update-postpayment {days=30 : Количество дней для постоплаты}';
    protected $description = 'Обновить период постоплаты в настройках биллинга';

    public function handle()
    {
        $days = (int) $this->argument('days');
        
        if ($days < 1) {
            $this->error('Количество дней должно быть больше 0');
            return 1;
        }

        $this->info("Обновление периода постоплаты на {$days} дней...");
        $this->newLine();

        // Получаем текущее значение
        $currentValue = BillingSettings::get('days_before_overdue', '7');
        $this->line("Текущее значение: {$currentValue} дней");

        // Обновляем через модель
        BillingSettings::set('days_before_overdue', (string) $days);

        // Также обновляем через прямой SQL запрос
        $updated = DB::table('billing_settings')
            ->where('key', 'days_before_overdue')
            ->update(['value' => (string) $days]);

        // Проверяем результат
        $newValue = BillingSettings::get('days_before_overdue');
        
        if ($newValue == (string) $days) {
            $this->info("✅ Настройка успешно обновлена!");
            $this->line("Новое значение: {$newValue} дней");
            $this->newLine();
            $this->comment("Теперь счета будут помечаться как просроченные через {$days} дней после создания.");
            return 0;
        } else {
            $this->error("❌ Ошибка при обновлении. Текущее значение: {$newValue}");
            $this->comment("Попробуйте выполнить SQL запрос вручную:");
            $this->comment("UPDATE billing_settings SET value = '{$days}' WHERE key = 'days_before_overdue';");
            return 1;
        }
    }
}












