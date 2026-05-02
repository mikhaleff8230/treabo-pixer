<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PlanSubscription;
use App\Models\SellerBalance;
use App\Models\Invoice;
use App\Models\Plan;
use Marvel\Database\Models\User;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Place;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AutoRenewSubscriptions extends Command
{
    protected $signature = 'billing:auto-renew';
    protected $description = 'Автопродление подписок на тарифы (1-го числа месяца)';

    public function handle()
    {
        $this->info('Проверка подписок для автопродления...');

        $now = Carbon::now();
        
        // Находим подписки, которые нужно продлить
        $subscriptionsToRenew = PlanSubscription::where('auto_renewal_enabled', true)
            ->where('status', 'active')
            ->where('auto_renewal_at', '<=', $now)
            ->with(['seller', 'plan'])
            ->get();

        $renewedCount = 0;
        $failedCount = 0;

        foreach ($subscriptionsToRenew as $subscription) {
            $seller = $subscription->seller;
            $plan = $subscription->plan;

            if (!$seller || !$plan) {
                $this->warn("Subscription {$subscription->id}: seller or plan not found");
                continue;
            }

            // Если тариф Free, не продлеваем
            if ($plan->name === 'Free') {
                $subscription->update(['auto_renewal_enabled' => false]);
                continue;
            }

            // Подсчитываем товары и плейсы для расчета стоимости
            $shops = $seller->shops;
            $totalActiveProducts = 0;
            
            foreach ($shops as $shop) {
                $activeCount = Product::where('shop_id', $shop->id)
                    ->where('status', 'publish')
                    ->count();
                $totalActiveProducts += $activeCount;
            }

            $totalPlaylists = Place::where('user_id', $seller->id)->count();

            // Рассчитываем стоимость нового периода
            $newPeriodStart = $now->copy()->startOfMonth();
            $newPeriodEnd = $now->copy()->endOfMonth();
            $amount = $plan->calculateMonthlyAmount($totalActiveProducts, $totalPlaylists);

            // Проверяем баланс
            $balance = SellerBalance::getOrCreate($seller->id);

            if ($balance->hasEnough($amount)) {
                // Списываем с баланса
                $balance->withdraw($amount);

                // Создаем новый период подписки
                $newSubscription = PlanSubscription::create([
                    'seller_id' => $seller->id,
                    'plan_id' => $plan->id,
                    'start_date' => $newPeriodStart,
                    'end_date' => $newPeriodEnd,
                    'amount' => $amount,
                    'is_proportional' => false,
                    'days_paid' => $now->daysInMonth,
                    'status' => 'active',
                    'auto_renewal_at' => $newPeriodEnd->copy()->addDay()->startOfDay(),
                    'auto_renewal_enabled' => true,
                ]);

                // Создаем счет
                $invoice = Invoice::create([
                    'seller_id' => $seller->id,
                    'plan_id' => $plan->id,
                    'period_start' => $newPeriodStart,
                    'period_end' => $newPeriodEnd,
                    'total_products' => $totalActiveProducts,
                    'total_places' => $totalPlaylists,
                    'price_per_product' => $totalActiveProducts > 0 ? $amount / $totalActiveProducts : 0,
                    'total_amount' => $amount,
                    'status' => 'paid',
                    'paid_at' => $now,
                ]);

                $newSubscription->invoice_id = $invoice->id;
                $newSubscription->save();

                // Помечаем старую подписку как истекшую
                $subscription->update(['status' => 'expired']);

                $renewedCount++;
                $this->info("Подписка {$subscription->id} продлена для продавца {$seller->id} ({$seller->name})");
            } else {
                // Недостаточно средств - отключаем автопродление
                $subscription->update(['auto_renewal_enabled' => false]);
                $failedCount++;
                $this->warn("Недостаточно средств для продления подписки {$subscription->id} продавца {$seller->id}. Требуется: {$amount} ₽, доступно: {$balance->balance} ₽");
                
                // Можно отправить уведомление продавцу
            }
        }

        $this->info("Автопродление завершено. Продлено: {$renewedCount}, не продлено (недостаточно средств): {$failedCount}");
        return 0;
    }
}




