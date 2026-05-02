<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Добавляем геолокационные поля в существующую таблицу products.
     * Согласно ТЗ: region_id ОБЯЗАТЕЛЬНО должен быть на уровне city.
     * geo_point_id — опционально, но рекомендуется.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Основные гео-поля
            $table->foreignId('region_id')->nullable()->constrained('regions');
            $table->foreignId('geo_point_id')->nullable()->constrained('geo_points');
            $table->text('address')->nullable();

            // Статусы публикации
            $table->boolean('is_active')->default(true)->after('status');
            $table->boolean('is_published')->default(false)->after('is_active');

            // Индексы согласно требованиям
            $table->index('region_id');
            $table->index(['is_active', 'is_published']);
            $table->index('created_at'); // для сортировки по новизне
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropForeign(['geo_point_id']);
            $table->dropColumn(['region_id', 'geo_point_id', 'address', 'is_active', 'is_published']);
            $table->dropIndex('products_region_id_index');
            $table->dropIndex('products_is_active_is_published_index');
            $table->dropIndex('products_created_at_index');
        });
    }
};
