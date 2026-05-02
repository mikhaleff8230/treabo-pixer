<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Хранит выбранную/определенную геолокацию пользователя (IP, GPS, manual).
     * Используется для персонализации выдачи товаров по умолчанию.
     */
    public function up(): void
    {
        Schema::create('user_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('region_id')->constrained('regions');
            $table->foreignId('geo_point_id')->nullable()->constrained('geo_points');
            $table->enum('source', ['ip', 'gps', 'manual'])->default('manual');
            $table->timestamps();

            $table->index(['user_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_locations');
    }
};
