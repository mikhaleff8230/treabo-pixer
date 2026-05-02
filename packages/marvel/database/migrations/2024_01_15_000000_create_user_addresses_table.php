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
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Основная информация об адресе
            $table->string('type')->default('pvz'); // 'pvz' или 'home'
            $table->string('title'); // "Работа", "Дом", "СДЭК на Тверской" и т.д.
            
            // Данные ПВЗ
            $table->string('pvz_id')->nullable(); // ID ПВЗ в системе службы
            $table->string('service')->nullable(); // 'sdek', 'yandex', '5post'
            $table->string('name')->nullable(); // Название ПВЗ
            
            // Адресные данные
            $table->string('city');
            $table->string('address');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            
            // Дополнительная информация
            $table->string('phone')->nullable();
            $table->string('work_time')->nullable();
            $table->text('note')->nullable(); // Заметки пользователя
            
            // Настройки
            $table->boolean('is_default')->default(false); // Адрес по умолчанию
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            // Индексы
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'is_default']);
            $table->index(['service', 'pvz_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
