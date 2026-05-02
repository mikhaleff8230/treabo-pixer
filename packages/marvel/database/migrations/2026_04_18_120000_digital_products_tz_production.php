<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_keys') && !Schema::hasColumn('product_keys', 'used_at')) {
            Schema::table('product_keys', function (Blueprint $table) {
                $table->timestamp('used_at')->nullable()->after('used_by');
            });
        }

        if (Schema::hasTable('product_subscriptions') && !Schema::hasTable('subscriptions')) {
            Schema::rename('product_subscriptions', 'subscriptions');
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' && Schema::hasColumn('products', 'digital_product_type')) {
            DB::statement("ALTER TABLE products MODIFY digital_product_type VARCHAR(50) NOT NULL DEFAULT 'file'");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscriptions') && !Schema::hasTable('product_subscriptions')) {
            Schema::rename('subscriptions', 'product_subscriptions');
        }

        if (Schema::hasTable('product_keys') && Schema::hasColumn('product_keys', 'used_at')) {
            Schema::table('product_keys', function (Blueprint $table) {
                $table->dropColumn('used_at');
            });
        }
    }
};
