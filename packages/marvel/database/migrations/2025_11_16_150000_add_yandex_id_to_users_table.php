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
        // Проверяем, существует ли таблица
        if (!Schema::hasTable('users')) {
            return; // Таблица не существует, пропускаем миграцию
        }
        
        Schema::table('users', function (Blueprint $table) {
            // Проверяем существование колонки перед добавлением
            if (!Schema::hasColumn('users', 'yandex_id')) {
                $table->string('yandex_id')->nullable()->unique()->after('email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'yandex_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('yandex_id');
            });
        }
    }
};












