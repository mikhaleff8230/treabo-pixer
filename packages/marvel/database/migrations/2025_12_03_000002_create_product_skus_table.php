<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductSkusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_skus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->string('sku')->nullable();
            $table->string('slug')->unique();
            $table->decimal('price', 10, 2);
            $table->decimal('old_price', 10, 2)->nullable();
            $table->integer('quantity')->default(0);
            $table->string('barcode')->nullable();
            $table->json('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('title')->nullable(); // Название варианта (например "S/Красный")
            $table->text('description')->nullable(); // Описание варианта
            $table->boolean('is_digital')->default(false);
            $table->boolean('is_disable')->default(false);
            $table->string('language')->default('ru');
            $table->json('meta')->nullable(); // для дополнительных полей
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('group_id')->references('id')->on('product_groups')->onDelete('cascade');
            
            // Индексы
            $table->index('group_id');
            $table->index('slug');
            $table->index('sku');
            $table->index('is_active');
            $table->index(['group_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_skus');
    }
}

