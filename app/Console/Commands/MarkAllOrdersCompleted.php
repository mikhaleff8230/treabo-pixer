<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Marvel\Database\Models\Order;
use Marvel\Enums\OrderStatus;
use Marvel\Enums\PaymentStatus;
use Illuminate\Support\Facades\DB;

class MarkAllOrdersCompleted extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:mark-all-completed 
                            {--dry-run : Показать количество заказов без обновления}
                            {--force : Принудительно обновить все заказы, включая уже выполненные}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Установить статус "выполнено" для всех заказов на сервере';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('Проверка заказов...');

        // Подсчитываем заказы для обновления
        $query = Order::query();
        
        if (!$force) {
            // Исключаем уже выполненные заказы
            $query->where('order_status', '!=', OrderStatus::COMPLETED);
        }

        $ordersCount = $query->count();

        if ($ordersCount === 0) {
            $this->info('Нет заказов для обновления.');
            return 0;
        }

        if ($dryRun) {
            $this->info("Найдено заказов для обновления: {$ordersCount}");
            $this->info("Статус заказа будет изменен на: " . OrderStatus::COMPLETED);
            $this->info("Статус оплаты будет изменен на: " . PaymentStatus::SUCCESS);
            
            // Показываем распределение по текущим статусам
            $statusDistribution = Order::select('order_status', DB::raw('count(*) as count'))
                ->groupBy('order_status')
                ->get();
            
            $this->info("\nТекущее распределение заказов по статусам:");
            foreach ($statusDistribution as $status) {
                $this->line("  - {$status->order_status}: {$status->count}");
            }
            
            return 0;
        }

        // Подтверждение
        if (!$this->confirm("Вы уверены, что хотите обновить {$ordersCount} заказов на статус 'выполнено'?")) {
            $this->info('Операция отменена.');
            return 0;
        }

        $this->info("Обновление заказов...");

        try {
            DB::beginTransaction();

            $updated = $query->update([
                'order_status' => OrderStatus::COMPLETED,
                'payment_status' => PaymentStatus::SUCCESS,
                'updated_at' => now()
            ]);

            DB::commit();

            $this->info("✓ Успешно обновлено заказов: {$updated}");
            $this->info("Статус заказа изменен на: " . OrderStatus::COMPLETED);
            $this->info("Статус оплаты изменен на: " . PaymentStatus::SUCCESS);

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Ошибка при обновлении заказов: " . $e->getMessage());
            return 1;
        }
    }
}

