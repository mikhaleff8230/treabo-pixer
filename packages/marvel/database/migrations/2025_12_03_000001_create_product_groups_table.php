<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Marvel\Enums\ProductStatus;

class CreateProductGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_groups', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('main_image')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('type_id')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable(); // manufacturer_id или author_id
            $table->string('brand_type')->nullable(); // 'manufacturer' или 'author'
            $table->enum('status', [
                ProductStatus::DRAFT,
                ProductStatus::PUBLISH,
                ProductStatus::APPROVED,
                ProductStatus::REJECTED,
                ProductStatus::UNPUBLISH,
                ProductStatus::UNDER_REVIEW,
            ])->default(ProductStatus::DRAFT);
            $table->string('language')->default('ru');
            $table->json('gallery')->nullable();
            $table->json('video')->nullable();
            $table->text('short_description')->nullable();
            $table->json('meta')->nullable(); // для дополнительных полей через Metable
            
            // Габариты и вес
            $table->string('height')->nullable();
            $table->string('length')->nullable();
            $table->string('width')->nullable();
            $table->decimal('weight', 10, 2)->nullable(); // вес в граммах
            
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->foreign('type_id')->references('id')->on('types')->onDelete('set null');
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');
            
            // Индексы
            $table->index('slug');
            $table->index('category_id');
            $table->index('shop_id');
            $table->index('status');
            $table->index(['language', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_groups');
    }
}

