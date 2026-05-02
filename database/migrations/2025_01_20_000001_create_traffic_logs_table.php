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
        Schema::create('traffic_logs', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->index();
            $table->string('user_agent')->nullable();
            $table->string('method', 10);
            $table->string('path');
            $table->string('referer')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('country_name')->nullable();
            $table->string('city')->nullable();
            $table->boolean('is_bot')->default(false)->index();
            $table->integer('response_time_ms')->nullable();
            $table->integer('status_code')->nullable();
            $table->integer('time_on_page')->nullable(); // в секундах
            $table->integer('page_depth')->default(1); // глубина просмотра
            $table->json('request_data')->nullable();
            $table->json('bot_signals')->nullable(); // признаки бота
            $table->timestamp('created_at')->index();
            
            // Индексы для быстрого поиска
            $table->index(['ip_address', 'created_at']);
            $table->index(['is_bot', 'created_at']);
            $table->index(['country_code', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('traffic_logs');
    }
};

