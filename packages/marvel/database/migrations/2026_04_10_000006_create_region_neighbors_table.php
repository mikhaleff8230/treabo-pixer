<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Определяет соседние регионы для быстрого поиска товаров "рядом".
     * Например: Москва и Московская область, Санкт-Петербург и Ленинградская область.
     */
    public function up(): void
    {
        Schema::create('region_neighbors', function (Blueprint $table) {
            $table->foreignId('region_id')->constrained('regions')->onDelete('cascade');
            $table->foreignId('neighbor_region_id')->constrained('regions')->onDelete('cascade');
            
            $table->primary(['region_id', 'neighbor_region_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('region_neighbors');
    }
};
