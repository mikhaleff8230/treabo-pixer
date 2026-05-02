<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductGroupAndSkuMetaTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Таблица мета-данных для product_groups
        if (!Schema::hasTable('product_groups_meta')) {
            Schema::create('product_groups_meta', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_group_id')->index();
                $table->string('type')->default('null');
                $table->string('key')->index();
                $table->text('value')->nullable();
                $table->timestamps();

                $table->foreign('product_group_id')
                    ->references('id')
                    ->on('product_groups')
                    ->onDelete('cascade');

                $table->index(['product_group_id', 'type']);
            });
        }

        // Таблица мета-данных для product_skus
        if (!Schema::hasTable('product_skus_meta')) {
            Schema::create('product_skus_meta', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_sku_id')->index();
                $table->string('type')->default('null');
                $table->string('key')->index();
                $table->text('value')->nullable();
                $table->timestamps();

                $table->foreign('product_sku_id')
                    ->references('id')
                    ->on('product_skus')
                    ->onDelete('cascade');

                $table->index(['product_sku_id', 'type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_skus_meta');
        Schema::dropIfExists('product_groups_meta');
    }
}

