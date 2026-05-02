<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'digital_product_type')) {
                $table->enum('digital_product_type', ['file', 'prompt', 'link', 'account', 'subscription', 'key'])
                    ->default('file')
                    ->after('is_digital');
            }

            if (!Schema::hasColumn('products', 'file_url')) {
                $table->text('file_url')->nullable()->after('digital_product_type');
            }
            if (!Schema::hasColumn('products', 'prompt_text')) {
                $table->longText('prompt_text')->nullable()->after('file_url');
            }
            if (!Schema::hasColumn('products', 'external_url')) {
                $table->text('external_url')->nullable()->after('prompt_text');
            }
            if (!Schema::hasColumn('products', 'account_data')) {
                $table->json('account_data')->nullable()->after('external_url');
            }
            if (!Schema::hasColumn('products', 'subscription_data')) {
                $table->json('subscription_data')->nullable()->after('account_data');
            }
            if (!Schema::hasColumn('products', 'key_data')) {
                $table->json('key_data')->nullable()->after('subscription_data');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $columns = [
                'key_data',
                'subscription_data',
                'account_data',
                'external_url',
                'prompt_text',
                'file_url',
                'digital_product_type',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

