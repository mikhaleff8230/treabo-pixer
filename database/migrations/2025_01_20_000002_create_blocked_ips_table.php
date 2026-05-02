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
        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->string('ip_range')->nullable(); // для блокировки диапазонов
            $table->enum('block_type', ['manual', 'automatic', 'rate_limit'])->default('automatic');
            $table->text('reason')->nullable();
            $table->integer('request_count')->default(0);
            $table->integer('bot_score')->default(0); // 0-100, чем выше, тем больше вероятность бота
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('blocked_at')->index();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('blocked_by')->nullable(); // ID администратора
            $table->timestamps();
            
            $table->index(['is_active', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocked_ips');
    }
};

