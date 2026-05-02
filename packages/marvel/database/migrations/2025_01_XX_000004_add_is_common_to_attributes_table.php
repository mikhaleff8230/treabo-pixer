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
        Schema::table('attributes', function (Blueprint $table) {
            // Добавляем поле is_common для атрибутов общих для всех товаров
            if (!Schema::hasColumn('attributes', 'is_common')) {
                $table->boolean('is_common')->default(false)->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            if (Schema::hasColumn('attributes', 'is_common')) {
                $table->dropColumn('is_common');
            }
        });
    }
};






