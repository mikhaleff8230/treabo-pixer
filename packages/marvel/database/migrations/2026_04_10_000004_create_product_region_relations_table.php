<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Таблица для расширенного покрытия продукта регионами (районы, метро, соседние города).
     * Позволяет показывать товар не только в своем городе, но и в соседних.
     */
    public function up(): void
    {
        Schema::create('product_region_relations', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('region_id')->constrained('regions')->onDelete('cascade');
            
            $table->primary(['product_id', 'region_id']);
            $table->timestamps();

            $table->index('region_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_region_relations');
    }
};
