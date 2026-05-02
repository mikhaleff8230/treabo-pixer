<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductSkuPropertyValuesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_sku_property_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sku_id');
            $table->unsignedBigInteger('property_id'); // attribute_id
            $table->unsignedBigInteger('property_value_id'); // attribute_value_id
            $table->timestamps();

            // Foreign keys
            $table->foreign('sku_id')->references('id')->on('product_skus')->onDelete('cascade');
            $table->foreign('property_id')->references('id')->on('attributes')->onDelete('cascade');
            $table->foreign('property_value_id')->references('id')->on('attribute_values')->onDelete('cascade');
            
            // Уникальность: один SKU не может иметь два одинаковых значения одного свойства
            $table->unique(['sku_id', 'property_id', 'property_value_id'], 'sku_property_value_unique');
            
            // Индексы
            $table->index('sku_id');
            $table->index('property_id');
            $table->index('property_value_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_sku_property_values');
    }
}

