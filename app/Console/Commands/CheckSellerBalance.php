<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SellerBalance;
use App\Models\BalanceDeposit;
use Marvel\Database\Models\User;

class CheckSellerBalance extends Command
{
    protected $signature = 'billing:check-balance {seller_id? : ID продавца (если не указан, покажет всех)}';
    protected $description = 'Проверить баланс продавца и историю пополнений';

    public function handle()
    {
        $sellerId = $this->argument('seller_id');

        if ($sellerId) {
            $this->checkSeller($sellerId);
        } else {
            $this->info('Проверяем всех продавцов с балансом...');
            $this->newLine();
            
            $balances = SellerBalance::with('seller')->get();
            
            if ($balances->isEmpty()) {
                $this->warn('Нет записей балансов.');
                return 0;
            }

            foreach ($balances as $balance) {
                $this->checkSeller($balance->seller_id);
                $this->newLine();
            }
        }

        return 0;
    }

    private function checkSeller($sellerId)
    {
        $seller = User::find($sellerId);
        if (!$seller) {
            $this->error("Продавец с ID {$sellerId} не найден!");
            return;
        }

        $balance = SellerBalance::getOrCreate($sellerId);
        $deposits = BalanceDeposit::where('seller_id', $sellerId)
            ->orderBy('created_at', 'desc')
            ->get();

        $sellerName = $seller->name ?: $seller->email;
        $this->info("=== Продавец ID: {$sellerId} ({$sellerName}) ===");
        $this->line("Текущий баланс: {$balance->balance} ₽");
        $this->line("Всего пополнено: {$balance->total_deposited} ₽");
        $this->line("Всего потрачено: {$balance->total_spent} ₽");
        $this->newLine();

        if ($deposits->isEmpty()) {
            $this->warn("  Нет записей пополнений");
        } else {
            $this->info("История пополнений ({$deposits->count()}):");
            foreach ($deposits as $deposit) {
                $statusIcon = $deposit->status === 'succeeded' ? '✓' : ($deposit->status === 'pending' ? '⏳' : '✗');
                $this->line("  {$statusIcon} ID: {$deposit->id}, Сумма: {$deposit->amount} ₽, Статус: {$deposit->status}, Payment ID: " . ($deposit->payment_id ?? 'NULL'));
            }
        }
    }
}

