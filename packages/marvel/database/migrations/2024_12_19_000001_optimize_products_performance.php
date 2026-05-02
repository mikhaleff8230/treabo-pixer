<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Проверяем существование таблицы products
        if (Schema::hasTable('products')) {
            $this->createIndexIfNotExists('products', 'idx_products_status_language', '(`status`, `language`)');
            $this->createIndexIfNotExists('products', 'idx_products_shop_status', '(`shop_id`, `status`)');
            $this->createIndexIfNotExists('products', 'idx_products_type_status', '(`type_id`, `status`)');
            $this->createIndexIfNotExists('products', 'idx_products_updated_status', '(`updated_at`, `status`)');
            $this->createIndexIfNotExists('products', 'idx_products_created_status', '(`created_at`, `status`)');
            $this->createIndexIfNotExists('products', 'idx_products_status_lang_updated', '(`status`, `language`, `updated_at`)');
            $this->createIndexIfNotExists('products', 'idx_products_name', '(`name`)');
            $this->createIndexIfNotExists('products', 'idx_products_slug', '(`slug`)');
        }

        // Проверяем существование таблицы category_product
        if (Schema::hasTable('category_product')) {
            $this->createIndexIfNotExists('category_product', 'idx_category_product_product_category', '(`product_id`, `category_id`)');
            $this->createIndexIfNotExists('category_product', 'idx_category_product_category_product', '(`category_id`, `product_id`)');
        }

        // Проверяем существование таблицы product_tag
        if (Schema::hasTable('product_tag')) {
            $this->createIndexIfNotExists('product_tag', 'idx_product_tag_product_tag', '(`product_id`, `tag_id`)');
            $this->createIndexIfNotExists('product_tag', 'idx_product_tag_tag_product', '(`tag_id`, `product_id`)');
        }

        // Проверяем существование таблицы orders
        if (Schema::hasTable('orders')) {
            $this->createIndexIfNotExists('orders', 'idx_orders_parent_created', '(`parent_id`, `created_at`)');
        }
    }

    /**
     * Create index if it doesn't exist
     */
    private function createIndexIfNotExists($table, $indexName, $columns)
    {
        $exists = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        
        if (empty($exists)) {
            DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` {$columns}");
            echo "Created index {$indexName} on table {$table}\n";
        } else {
            echo "Index {$indexName} already exists on table {$table}\n";
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_status_language');
            $table->dropIndex('idx_products_shop_status');
            $table->dropIndex('idx_products_type_status');
            $table->dropIndex('idx_products_updated_status');
            $table->dropIndex('idx_products_created_status');
            $table->dropIndex('idx_products_status_lang_updated');
            $table->dropIndex('idx_products_name');
            $table->dropIndex('idx_products_slug');
        });

        if (Schema::hasTable('category_product')) {
            Schema::table('category_product', function (Blueprint $table) {
                $table->dropIndex('idx_category_product_product_category');
                $table->dropIndex('idx_category_product_category_product');
            });
        }

        if (Schema::hasTable('product_tag')) {
            Schema::table('product_tag', function (Blueprint $table) {
                $table->dropIndex('idx_product_tag_product_tag');
                $table->dropIndex('idx_product_tag_tag_product');
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_parent_created');
        });
    }
};
