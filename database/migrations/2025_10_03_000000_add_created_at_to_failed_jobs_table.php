<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        // Проверяем, существует ли уже колонка created_at
        if (!Schema::hasColumn('failed_jobs', 'created_at')) {
            // Добавляем колонку created_at
            Schema::table('failed_jobs', function (Blueprint $table) {
                $table->timestamp('created_at')->nullable()->after('failed_at');
            });
            
            // Обновляем существующие записи
            DB::statement('UPDATE failed_jobs SET created_at = failed_at WHERE created_at IS NULL');
            
            // Делаем колонку NOT NULL через прямой SQL (избегаем проблемы с Doctrine DBAL)
            DB::statement('ALTER TABLE failed_jobs MODIFY created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->dropColumn('created_at');
        });
    }
};

