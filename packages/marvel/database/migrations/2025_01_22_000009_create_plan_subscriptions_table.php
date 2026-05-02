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
        Schema::create('plan_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('plans')->onDelete('restrict');
            $table->date('start_date')->comment('Дата начала подписки');
            $table->date('end_date')->comment('Дата окончания подписки');
            $table->decimal('amount', 10, 2)->comment('Сумма оплаты');
            $table->boolean('is_proportional')->default(false)->comment('Пропорциональная оплата');
            $table->integer('days_paid')->nullable()->comment('Количество оплаченных дней');
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->onDelete('set null');
            $table->timestamp('auto_renewal_at')->nullable()->comment('Дата следующего автопродления');
            $table->boolean('auto_renewal_enabled')->default(true)->comment('Включено ли автопродление');
            $table->timestamps();

            $table->index(['seller_id', 'status']);
            $table->index(['end_date', 'status']);
            $table->index('auto_renewal_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_subscriptions');
    }
};

