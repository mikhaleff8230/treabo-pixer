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
        Schema::table('settings', function (Blueprint $table) {
            // Проверяем, не существует ли уже колонка
            if (!Schema::hasColumn('settings', 'active_payment_gateway')) {
                $table->string('active_payment_gateway')->default('yookassa');
            }
            
            if (!Schema::hasColumn('settings', 'yookassa_settings')) {
                $table->json('yookassa_settings')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'active_payment_gateway')) {
                $table->dropColumn('active_payment_gateway');
            }
            
            if (Schema::hasColumn('settings', 'yookassa_settings')) {
                $table->dropColumn('yookassa_settings');
            }
        });
    }
};

