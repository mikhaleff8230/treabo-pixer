<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Добавляем начальные настройки
        DB::table('billing_settings')->insert([
            ['key' => 'price_per_product', 'value' => '5.00', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'currency', 'value' => 'RUB', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'auto_generation', 'value' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'generation_day', 'value' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'days_before_overdue', 'value' => '30', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'overdue_action', 'value' => 'hide_products', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};



