<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use Marvel\Database\Models\User;
use Marvel\Database\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CheckPlanStatus extends Command
{
    protected $signature = 'billing:check-plan-status';
    protected $description = 'Проверить статус тарифов и деактивировать функции при неоплате';

    public function handle()
    {
        $this->info('Проверка статуса тарифов...');

        $now = Carbon::now();
        $currentPeriodStart = $now->copy()->startOfMonth();
        $currentPeriodEnd = $now->copy()->endOfMonth();

        // Получаем всех продавцов с тарифами (кроме Free)
        $sellers = User::whereHas('plan', function ($query) {
            $query->where('name', '!=', 'Free');
        })->with('plan')->get();

        $deactivatedCount = 0;

        foreach ($sellers as $seller) {
            // Проверяем, оплачен ли тариф за текущий период
            $isPaid = $seller->isPlanPaidForCurrentPeriod();

            if (!$isPaid) {
                $this->warn("Тариф продавца {$seller->id} ({$seller->name}) не оплачен за текущий период");

                // Проверяем, есть ли просроченные счета
                $overdueInvoices = Invoice::where('seller_id', $seller->id)
                    ->where('status', 'overdue')
                    ->count();

                if ($overdueInvoices > 0) {
                    // Если есть просроченные счета, скрываем товары (если еще не скрыты)
                    $shops = $seller->shops;
                    foreach ($shops as $shop) {
                        $hidden = Product::where('shop_id', $shop->id)
                            ->where('status', 'publish')
                            ->update(['status' => 'unpublish']);
                        
                        if ($hidden > 0) {
                            $this->info("  Скрыто {$hidden} товаров для продавца {$seller->id}");
                        }
                    }
                }

                $deactivatedCount++;
            }
        }

        $this->info("Проверка завершена. Найдено {$deactivatedCount} продавцов с неоплаченными тарифами.");
        return 0;
    }
}




