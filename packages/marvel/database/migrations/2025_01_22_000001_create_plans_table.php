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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Название тарифа: Free, Standard, Pro');
            $table->decimal('price', 10, 2)->default(0)->comment('Стоимость в ₽');
            $table->integer('limit_products')->default(0)->comment('Лимит товаров');
            $table->integer('limit_playlists')->default(0)->comment('Лимит плейсов/видео');
            $table->decimal('extra_product_price', 8, 2)->nullable()->comment('Стоимость доп. товара');
            $table->decimal('extra_playlist_price', 8, 2)->nullable()->comment('Стоимость доп. плейса');
            $table->boolean('link_ozon_wb')->default(false)->comment('Возможность ссылок на Ozon/WB');
            $table->boolean('utm_tracking')->nullable()->comment('UTM-метки (только Pro)');
            $table->boolean('chat_enabled')->nullable()->comment('Доступ к чату (только Pro)');
            $table->boolean('featured_collections')->nullable()->comment('Попадание в подборки (только Pro)');
            $table->timestamps();

        });

        // Данные будут добавлены через Seeder
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};

