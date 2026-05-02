<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\BillingSettings;
use Marvel\Database\Models\User;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Shop;
use Marvel\Database\Models\Place;
use Illuminate\Support\Facades\DB;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'billing:generate-monthly';
    protected $description = 'Generate monthly invoices for sellers';

    public function handle()
    {
        // Проверяем, включена ли автоматическая генерация
        $autoGeneration = (bool) BillingSettings::get('auto_generation', true);
        
        if (!$autoGeneration) {
            $this->warn('Автоматическая генерация счетов отключена в настройках.');
            $this->info('Для включения выполните: UPDATE billing_settings SET value = \'1\' WHERE key = \'auto_generation\';');
            return 0;
        }

        $this->info('Generating monthly invoices...');

        $periodStart = now()->subMonth()->startOfMonth();
        $periodEnd = now()->subMonth()->endOfMonth();

        // Получаем всех пользователей, у которых есть магазины (продавцы)
        $sellers = User::whereHas('shops', function ($query) {
            $query->whereHas('products', function ($q) {
                $q->where('status', 'publish'); // Активные товары
            });
        })->with('plan')->get();

        $invoicesCreated = 0;

        foreach ($sellers as $seller) {
            // Получаем все магазины продавца
            $shops = $seller->shops;
            
            $totalActiveProducts = 0;
            
            // Подсчитываем активные товары во всех магазинах продавца
            foreach ($shops as $shop) {
                $activeCount = Product::where('shop_id', $shop->id)
                    ->where('status', 'publish')
                    ->count();
                
                $totalActiveProducts += $activeCount;
            }

            // Подсчитываем плейсы продавца
            $totalPlaylists = Place::where('user_id', $seller->id)->count();

            // Если нет товаров и нет плейсов, пропускаем
            if ($totalActiveProducts === 0 && $totalPlaylists === 0) {
                continue;
            }

            // Проверяем, не создан ли уже счёт за этот период
            $existingInvoice = Invoice::where('seller_id', $seller->id)
                ->where('period_start', $periodStart)
                ->where('period_end', $periodEnd)
                ->first();

            if ($existingInvoice) {
                $this->warn("Invoice already exists for seller {$seller->id} for period {$periodStart->format('Y-m-d')} - {$periodEnd->format('Y-m-d')}");
                continue;
            }

            // Получаем тарифный план продавца или используем тариф по умолчанию
            $plan = $seller->plan ?? Plan::getDefault();

            // Рассчитываем сумму по тарифному плану
            if ($plan) {
                $totalAmount = $plan->calculateMonthlyAmount($totalActiveProducts, $totalPlaylists);
            } else {
                // Если тарифа нет, используем старую логику для обратной совместимости
                $totalAmount = Invoice::calculateLegacyTariffAmount($totalActiveProducts);
                $this->warn("Seller {$seller->id} has no plan, using legacy tariff calculation");
            }
            
            // Для совместимости сохраняем среднюю цену за товар
            $averagePricePerProduct = $totalActiveProducts > 0 ? $totalAmount / $totalActiveProducts : 0;

            // Создаём счёт
            Invoice::create([
                'seller_id' => $seller->id,
                'plan_id' => $plan?->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'total_products' => $totalActiveProducts,
                'total_places' => $totalPlaylists,
                'price_per_product' => $averagePricePerProduct,
                'total_amount' => $totalAmount,
                'status' => 'pending',
            ]);

            $planName = $plan ? $plan->name : 'LEGACY';
            $invoicesCreated++;
            $this->info("Created invoice for seller {$seller->id} ({$seller->name}): Plan={$planName}, Products={$totalActiveProducts}, Playlists={$totalPlaylists}, Amount={$totalAmount} RUB");
        }

        $this->info("Monthly invoices generated successfully. Created: {$invoicesCreated} invoices.");
        return 0;
    }
}



