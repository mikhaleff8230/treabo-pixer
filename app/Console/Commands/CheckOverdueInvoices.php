<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\BillingSettings;
use Marvel\Database\Models\Product;
use Illuminate\Support\Facades\DB;

class CheckOverdueInvoices extends Command
{
    protected $signature = 'billing:check-overdue';
    protected $description = 'Check and mark overdue invoices, hide products';

    public function handle()
    {
        $this->info('Checking overdue invoices...');

        $daysBeforeOverdue = (int) BillingSettings::get('days_before_overdue', 30);
        $overdueAction = BillingSettings::get('overdue_action', 'hide_products');

        // Находим счета со статусом pending, старше указанного количества дней
        $overdueInvoices = Invoice::where('status', 'pending')
            ->where('created_at', '<=', now()->subDays($daysBeforeOverdue))
            ->get();

        $markedOverdue = 0;
        $productsHidden = 0;

        foreach ($overdueInvoices as $invoice) {
            // Меняем статус на overdue
            $invoice->update(['status' => 'overdue']);
            $markedOverdue++;

            $this->info("Marked invoice {$invoice->id} as overdue for seller {$invoice->seller_id}");

            // Переводим товары продавца в inactive
            if ($overdueAction === 'hide_products') {
                $seller = $invoice->seller;
                $shops = $seller->shops;

                foreach ($shops as $shop) {
                    $hidden = Product::where('shop_id', $shop->id)
                        ->where('status', 'publish')
                        ->update(['status' => 'unpublish']);

                    $productsHidden += $hidden;
                }

                $this->info("Hidden {$productsHidden} products for seller {$invoice->seller_id}");
            }
        }

        $this->info("Overdue check completed. Marked {$markedOverdue} invoices as overdue, hidden {$productsHidden} products.");
        return 0;
    }
}





