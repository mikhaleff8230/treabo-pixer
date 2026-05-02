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
        // Обновляем enum для display_type, добавляя 'range'
        // MySQL не поддерживает ALTER ENUM напрямую, поэтому используем MODIFY COLUMN
        if (Schema::hasColumn('attributes', 'display_type')) {
            DB::statement("ALTER TABLE `attributes` MODIFY COLUMN `display_type` ENUM(
                'input',
                'dropdown',
                'radio',
                'checkbox',
                'color_swatch',
                'image_swatch',
                'toggle',
                'range'
            ) DEFAULT 'input'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Возвращаем enum без 'range'
        if (Schema::hasColumn('attributes', 'display_type')) {
            DB::statement("ALTER TABLE `attributes` MODIFY COLUMN `display_type` ENUM(
                'input',
                'dropdown',
                'radio',
                'checkbox',
                'color_swatch',
                'image_swatch',
                'toggle'
            ) DEFAULT 'input'");
        }
    }
};

