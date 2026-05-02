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
        Schema::create('seller_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->decimal('balance', 10, 2)->default(0)->comment('Баланс продавца в рублях');
            $table->decimal('total_deposited', 10, 2)->default(0)->comment('Всего пополнено');
            $table->decimal('total_spent', 10, 2)->default(0)->comment('Всего потрачено');
            $table->timestamps();

            $table->index('seller_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_balances');
    }
};

