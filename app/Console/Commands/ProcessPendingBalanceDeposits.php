<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BalanceDeposit;
use App\Models\SellerBalance;
use App\Services\YooKassa\YooKassaService;
use App\Services\YooKassa\YooKassaConfig;
use Illuminate\Support\Facades\Log;

class ProcessPendingBalanceDeposits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:process-pending-deposits {--force : Принудительно обработать все pending платежи}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Проверить и обработать pending пополнения баланса, которые уже оплачены';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Начинаем проверку pending пополнений баланса...');

        $query = BalanceDeposit::where('status', 'pending')
            ->whereNotNull('payment_id');

        if (!$this->option('force')) {
            // Проверяем только те, что созданы более 5 минут назад (чтобы дать время на webhook)
            $query->where('created_at', '<', now()->subMinutes(5));
        }

        $deposits = $query->get();

        if ($deposits->isEmpty()) {
            $this->info('Нет pending пополнений для обработки.');
            return 0;
        }

        $this->info("Найдено {$deposits->count()} pending пополнений.");

        $shopId = config('services.yookassa.shop_id');
        $secretKey = config('services.yookassa.secret_key');
        $isTest = config('services.yookassa.is_test', false);

        if (empty($shopId) || empty($secretKey)) {
            $this->error('YooKassa не настроен!');
            return 1;
        }

        $config = new YooKassaConfig($shopId, $secretKey, $isTest);
        $service = new YooKassaService($config);

        $processed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($deposits as $deposit) {
            try {
                $this->line("Проверяем платеж {$deposit->payment_id} (ID: {$deposit->id}, Сумма: {$deposit->amount} ₽)...");

                // Проверяем статус платежа в YooKassa
                $paymentInfo = $service->checkPayment($deposit->payment_id);

                if ($paymentInfo['status'] === 'succeeded' && ($paymentInfo['paid'] ?? false)) {
                    // Платёж успешен - пополняем баланс
                    $balance = SellerBalance::getOrCreate($deposit->seller_id);
                    $oldBalance = $balance->balance;
                    
                    $balance->deposit($deposit->amount, "Пополнение баланса (обработка pending платежа)");
                    
                    $deposit->update([
                        'status' => 'succeeded',
                        'paid_at' => now()
                    ]);

                    $this->info("✓ Баланс пополнен! Продавец ID: {$deposit->seller_id}, Сумма: {$deposit->amount} ₽, Баланс: {$oldBalance} → {$balance->balance} ₽");
                    $processed++;

                    Log::info('ProcessPendingBalanceDeposits: Баланс пополнен', [
                        'deposit_id' => $deposit->id,
                        'seller_id' => $deposit->seller_id,
                        'amount' => $deposit->amount,
                        'payment_id' => $deposit->payment_id,
                        'old_balance' => $oldBalance,
                        'new_balance' => $balance->balance
                    ]);
                } elseif ($paymentInfo['status'] === 'pending' || $paymentInfo['status'] === 'waiting_for_capture') {
                    $this->warn("  Платеж еще не завершен (статус: {$paymentInfo['status']})");
                    $skipped++;
                } elseif ($paymentInfo['status'] === 'canceled') {
                    $this->warn("  Платеж отменен");
                    $deposit->update(['status' => 'canceled']);
                    $skipped++;
                } else {
                    $this->warn("  Неизвестный статус: {$paymentInfo['status']}");
                    $skipped++;
                }
            } catch (\Exception $e) {
                $this->error("  Ошибка при обработке платежа {$deposit->payment_id}: " . $e->getMessage());
                $failed++;
                
                Log::error('ProcessPendingBalanceDeposits: Ошибка', [
                    'deposit_id' => $deposit->id,
                    'payment_id' => $deposit->payment_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->newLine();
        $this->info("Обработка завершена:");
        $this->info("  ✓ Обработано: {$processed}");
        $this->info("  ⊘ Пропущено: {$skipped}");
        if ($failed > 0) {
            $this->error("  ✗ Ошибок: {$failed}");
        }

        return 0;
    }
}

