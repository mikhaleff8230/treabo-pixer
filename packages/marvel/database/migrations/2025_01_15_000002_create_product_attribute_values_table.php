<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('product_attribute_values');
        
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('attribute_id');
            $table->string('value', 500); // Прямое значение атрибута для товара
            $table->unsignedBigInteger('attribute_value_id')->nullable(); // Ссылка на предустановленное значение
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('attribute_id')->references('id')->on('attributes')->onDelete('cascade');
            $table->foreign('attribute_value_id')->references('id')->on('attribute_values')->onDelete('set null');
            
            // Уникальная связь товар-атрибут
            $table->unique(['product_id', 'attribute_id']);
            
            // Индексы для быстрого поиска и фильтрации
            $table->index('attribute_id');
            $table->index('value');
            $table->index('product_id');
            $table->index('attribute_value_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_attribute_values');
    }
};
