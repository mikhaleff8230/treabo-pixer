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
        Schema::create('additional_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['product', 'playlist'])->comment('Тип покупки: товар или плейс');
            $table->integer('quantity')->default(1)->comment('Количество');
            $table->decimal('price_per_unit', 8, 2)->comment('Цена за единицу');
            $table->decimal('total_amount', 10, 2)->comment('Общая сумма');
            $table->decimal('discount', 8, 2)->default(0)->comment('Скидка (если есть)');
            $table->foreignId('plan_id')->nullable()->constrained('plans')->onDelete('set null')->comment('Тариф, по которому была скидка');
            $table->enum('payment_method', ['balance', 'yookassa', 'direct'])->default('balance')->comment('Способ оплаты');
            $table->string('payment_id')->nullable()->comment('ID платежа в YooKassa');
            $table->enum('status', ['pending', 'paid', 'failed'])->default('pending');
            $table->date('valid_until')->nullable()->comment('Действительно до (для плейсов - до конца месяца)');
            $table->timestamps();

            $table->index(['seller_id', 'status']);
            $table->index('valid_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('additional_purchases');
    }
};

