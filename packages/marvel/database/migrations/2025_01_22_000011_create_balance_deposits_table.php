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
        Schema::create('balance_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade')->comment('ID продавца');
            $table->decimal('amount', 10, 2)->comment('Сумма пополнения');
            $table->string('payment_id')->nullable()->unique()->comment('ID платежа в YooKassa');
            $table->enum('status', ['pending', 'succeeded', 'canceled'])->default('pending')->comment('Статус платежа');
            $table->timestamp('paid_at')->nullable()->comment('Дата оплаты');
            $table->timestamps();

            $table->index('seller_id');
            $table->index('payment_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance_deposits');
    }
};

