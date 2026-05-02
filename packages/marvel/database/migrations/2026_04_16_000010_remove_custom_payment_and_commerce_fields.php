<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Нормализуем legacy-статусы, чтобы их можно было убрать из ENUM.
        DB::table('orders')->whereIn('order_status', ['created', 'awaiting_payment'])->update([
            'order_status' => 'order-pending',
        ]);
        DB::table('orders')->whereIn('order_status', [
            'paid_prepayment',
            'in_progress',
            'completed_by_master',
            'awaiting_final_payment',
            'paid_full',
            'shipped',
        ])->update([
            'order_status' => 'order-processing',
        ]);
        DB::table('orders')->where('order_status', 'done')->update([
            'order_status' => 'order-completed',
        ]);

        // Возвращаем только стандартные статусы заказов без кастомного flow аванса/доплаты.
        DB::statement("
            ALTER TABLE orders
            MODIFY COLUMN order_status ENUM(
                'order-pending',
                'order-processing',
                'order-completed',
                'order-refunded',
                'order-failed',
                'order-cancelled',
                'order-at-local-facility',
                'order-out-for-delivery'
            ) NOT NULL DEFAULT 'order-pending'
        ");

        Schema::table('orders', function (Blueprint $table) {
            $columns = [
                'order_type',
                'payment_mode',
                'prepayment_amount',
                'remaining_amount',
                'estimated_days',
                'platform_fee',
                'master_payout',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $columns = [
                'commerce_product_type',
                'estimated_days',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        // Откат не восстанавливает кастомные поля/статусы намеренно:
        // функционал кастомной оплаты удален по бизнес-требованию.
    }
};

