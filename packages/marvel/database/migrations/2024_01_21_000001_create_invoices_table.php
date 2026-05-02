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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('total_products');
            $table->decimal('price_per_product', 8, 2)->default(5.00);
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['pending', 'paid', 'overdue'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_id')->nullable(); // ID платежа в YooKassa
            $table->timestamps();

            $table->index(['seller_id', 'status']);
            $table->index(['period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

