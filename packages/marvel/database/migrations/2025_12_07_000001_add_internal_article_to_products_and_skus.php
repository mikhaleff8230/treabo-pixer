<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInternalArticleToProductsAndSkus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Добавляем поле internal_article в таблицу products
        Schema::table('products', function (Blueprint $table) {
            $table->string('internal_article', 20)->unique()->nullable()->after('sku');
            $table->index('internal_article');
        });

        // Добавляем поле internal_article в таблицу product_skus
        Schema::table('product_skus', function (Blueprint $table) {
            $table->string('internal_article', 20)->unique()->nullable()->after('sku');
            $table->index('internal_article');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['internal_article']);
            $table->dropColumn('internal_article');
        });

        Schema::table('product_skus', function (Blueprint $table) {
            $table->dropIndex(['internal_article']);
            $table->dropColumn('internal_article');
        });
    }
}

