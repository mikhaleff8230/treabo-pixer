<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Таблица для кэширования результатов геолокации по IP-адресам.
     * Позволяет избежать повторных запросов к DaData API и экономить лимит (10 000 запросов/день).
     * 
     * Рекомендация DaData: "Запоминать результат, который вернула «Дадата» — и не делать повторных вызовов"
     */
    public function up(): void
    {
        Schema::create('geo_ip_cache', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique()->index(); // IPv4 или IPv6
            $table->string('city')->nullable()->index();
            $table->string('city_with_type')->nullable();
            $table->string('region')->nullable();
            $table->string('region_with_type')->nullable();
            $table->string('state_name')->nullable();
            $table->string('country', 100)->nullable()->index();
            $table->string('iso_code', 2)->nullable()->index(); // ISO код страны (RU, US, etc)
            $table->string('region_iso_code', 10)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('federal_district')->nullable();
            
            // Координаты
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lon', 11, 8)->nullable();
            
            // Идентификаторы
            $table->string('kladr_id', 20)->nullable()->index();
            $table->string('city_kladr_id', 20)->nullable();
            $table->string('region_kladr_id', 20)->nullable();
            $table->string('fias_id', 36)->nullable()->index(); // UUID
            $table->string('city_fias_id', 36)->nullable();
            $table->string('region_fias_id', 36)->nullable();
            
            // Дополнительная информация
            $table->string('timezone', 50)->nullable();
            $table->string('source', 50)->nullable()->index(); // dadata, maxmind, yandex, etc
            $table->text('full_address')->nullable();
            $table->text('unrestricted_value')->nullable();
            
            // Метаданные
            $table->integer('request_count')->default(1)->index(); // Количество использований
            $table->timestamp('last_used_at')->nullable()->index(); // Последнее использование
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            
            // Индексы для быстрого поиска
            $table->index(['country', 'city']);
            $table->index(['source', 'last_used_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geo_ip_cache');
    }
};

