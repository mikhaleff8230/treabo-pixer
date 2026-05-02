<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BalanceDeposit;
use Illuminate\Support\Facades\DB;

class CheckBalanceDeposits extends Command
{
    protected $signature = 'billing:check-deposits';
    protected $description = 'Проверить все записи пополнения баланса';

    public function handle()
    {
        $this->info('Проверяем все записи пополнения баланса...');
        $this->newLine();

        $deposits = BalanceDeposit::orderBy('created_at', 'desc')->get();

        if ($deposits->isEmpty()) {
            $this->warn('Нет записей пополнения баланса.');
            return 0;
        }

        $this->info("Всего записей: {$deposits->count()}");
        $this->newLine();

        $table = [];
        foreach ($deposits as $deposit) {
            $table[] = [
                'ID' => $deposit->id,
                'Seller ID' => $deposit->seller_id,
                'Сумма' => $deposit->amount . ' ₽',
                'Payment ID' => $deposit->payment_id ?? 'NULL',
                'Статус' => $deposit->status,
                'Создано' => $deposit->created_at->format('d.m.Y H:i'),
                'Оплачено' => $deposit->paid_at ? $deposit->paid_at->format('d.m.Y H:i') : '-',
            ];
        }

        $this->table(['ID', 'Seller ID', 'Сумма', 'Payment ID', 'Статус', 'Создано', 'Оплачено'], $table);

        // Статистика
        $this->newLine();
        $this->info('Статистика:');
        $this->line("  Pending: " . $deposits->where('status', 'pending')->count());
        $this->line("  Succeeded: " . $deposits->where('status', 'succeeded')->count());
        $this->line("  Canceled: " . $deposits->where('status', 'canceled')->count());
        $this->line("  Без payment_id: " . $deposits->whereNull('payment_id')->count());

        return 0;
    }
}

