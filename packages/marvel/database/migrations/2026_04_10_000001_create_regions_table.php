<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Создает иерархическую таблицу регионов (страна → регион → город → район).
     * Это основа всей геолокационной системы. 
     * Используется вместо хранения строковых названий городов в продуктах.
     */
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('regions')->onDelete('cascade');
            $table->enum('type', ['country', 'region', 'city', 'district']);
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('fias_code')->nullable();
            $table->string('yandex_region_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Индексы согласно требованиям
            $table->index('parent_id');
            $table->index('slug');
            $table->index('type');
            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
