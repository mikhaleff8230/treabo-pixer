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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            
            // Основная информация об отправлении
            $table->string('service'); // 'sdek', 'yandex', '5post'
            $table->string('external_id')->unique(); // UUID отправления в системе службы
            $table->string('tracking_number')->nullable(); // Трек-номер
            $table->string('barcode')->nullable(); // Штрих-код
            
            // Статус отправления
            $table->string('status')->default('created'); // created, shipped, in_transit, delivered, cancelled
            $table->string('external_status')->nullable(); // Статус в системе службы
            $table->text('status_description')->nullable(); // Описание статуса
            
            // Адреса
            $table->json('sender_address'); // Адрес отправителя
            $table->json('recipient_address'); // Адрес получателя (ПВЗ или адрес)
            
            // Данные о посылке
            $table->json('package_info'); // Размеры, вес, содержимое
            $table->decimal('declared_value', 10, 2)->nullable(); // Объявленная стоимость
            $table->decimal('delivery_cost', 10, 2)->nullable(); // Стоимость доставки
            
            // Получатель
            $table->json('recipient_info'); // ФИО, телефон, email получателя
            
            // Дополнительные услуги
            $table->json('services')->nullable(); // Дополнительные услуги
            $table->boolean('cash_on_delivery')->default(false); // Наложенный платеж
            $table->decimal('cod_amount', 10, 2)->nullable(); // Сумма наложенного платежа
            
            // Даты
            $table->timestamp('shipped_at')->nullable(); // Дата отправки
            $table->timestamp('estimated_delivery')->nullable(); // Ожидаемая дата доставки
            $table->timestamp('delivered_at')->nullable(); // Дата доставки
            
            // Метаданные
            $table->json('api_response')->nullable(); // Полный ответ API
            $table->json('tracking_events')->nullable(); // События отслеживания
            $table->text('notes')->nullable(); // Заметки
            
            $table->timestamps();
            
            // Индексы
            $table->index(['order_id']);
            $table->index(['service', 'external_id']);
            $table->index(['tracking_number']);
            $table->index(['status']);
            $table->index(['shipped_at']);
            $table->index(['delivered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
