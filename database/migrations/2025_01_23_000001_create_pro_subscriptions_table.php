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
        Schema::create('pro_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->decimal('amount', 10, 2)->default(1990.00)->comment('Стоимость подписки в ₽');
            $table->date('start_date')->comment('Дата начала подписки');
            $table->date('end_date')->comment('Дата окончания подписки (30 дней от start_date)');
            $table->string('status')->default('active')->comment('active, expired, canceled');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['seller_id', 'status']);
            $table->index('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pro_subscriptions');
    }
};

