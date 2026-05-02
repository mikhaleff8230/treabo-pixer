<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
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
                'order-out-for-delivery',
                'created',
                'awaiting_payment',
                'paid_prepayment',
                'in_progress',
                'completed_by_master',
                'awaiting_final_payment',
                'paid_full',
                'shipped',
                'done'
            ) NOT NULL DEFAULT 'order-pending'
        ");

        Schema::table('products', function (Blueprint $table) {
            // NOTE: `product_type` already exists in this project for simple/variable.
            // To avoid breaking ready-products flow, we store commerce type separately.
            if (!Schema::hasColumn('products', 'commerce_product_type')) {
                $table->enum('commerce_product_type', ['ready', 'custom'])
                    ->default('ready')
                    ->after('product_type');
            }

            if (!Schema::hasColumn('products', 'estimated_days')) {
                $table->unsignedInteger('estimated_days')->nullable()->after('commerce_product_type');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'order_type')) {
                $table->enum('order_type', ['ready', 'custom'])
                    ->default('ready')
                    ->after('payment_status');
            }

            if (!Schema::hasColumn('orders', 'payment_mode')) {
                $table->enum('payment_mode', ['full', 'prepayment'])
                    ->nullable()
                    ->after('order_type');
            }

            if (!Schema::hasColumn('orders', 'prepayment_amount')) {
                $table->decimal('prepayment_amount', 12, 2)
                    ->nullable()
                    ->after('payment_mode');
            }

            if (!Schema::hasColumn('orders', 'remaining_amount')) {
                $table->decimal('remaining_amount', 12, 2)
                    ->nullable()
                    ->after('prepayment_amount');
            }

            if (!Schema::hasColumn('orders', 'estimated_days')) {
                $table->unsignedInteger('estimated_days')
                    ->nullable()
                    ->after('remaining_amount');
            }

            if (!Schema::hasColumn('orders', 'platform_fee')) {
                $table->decimal('platform_fee', 12, 2)
                    ->nullable()
                    ->after('estimated_days');
            }

            if (!Schema::hasColumn('orders', 'master_payout')) {
                $table->decimal('master_payout', 12, 2)
                    ->nullable()
                    ->after('platform_fee');
            }
        });
    }

    public function down(): void
    {
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

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'estimated_days')) {
                $table->dropColumn('estimated_days');
            }
            if (Schema::hasColumn('products', 'commerce_product_type')) {
                $table->dropColumn('commerce_product_type');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            $columns = [
                'master_payout',
                'platform_fee',
                'estimated_days',
                'remaining_amount',
                'prepayment_amount',
                'payment_mode',
                'order_type',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
