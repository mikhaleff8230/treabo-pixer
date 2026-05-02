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
        Schema::create('bot_statistics', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique()->index();
            $table->integer('total_requests')->default(0);
            $table->integer('bot_requests')->default(0);
            $table->integer('human_requests')->default(0);
            $table->integer('blocked_requests')->default(0);
            $table->integer('unique_ips')->default(0);
            $table->integer('blocked_ips_count')->default(0);
            $table->json('top_ips')->nullable(); // топ IP адресов
            $table->json('top_user_agents')->nullable(); // топ User-Agent
            $table->json('top_countries')->nullable(); // топ стран
            $table->json('top_paths')->nullable(); // топ путей
            $table->integer('avg_response_time')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_statistics');
    }
};

