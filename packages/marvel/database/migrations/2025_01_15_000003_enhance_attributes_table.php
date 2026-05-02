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
            // Добавляем поле type если его нет
            if (!Schema::hasColumn('attributes', 'type')) {
                $table->string('type')->nullable()->after('name');
            }
            
            // Добавляем поле для определения типа ввода (если еще не добавлено)
            if (!Schema::hasColumn('attributes', 'input_type')) {
                $table->enum('input_type', [
                    'text', 
                    'number', 
                    'boolean', 
                    'select', 
                    'multiselect',
                    'textarea',
                    'date',
                    'url',
                    'email'
                ])->default('text')->after('type');
            }
            
            // Добавляем поле для определения обязательности
            if (!Schema::hasColumn('attributes', 'is_required')) {
                $table->boolean('is_required')->default(false)->after('input_type');
            }
            
            // Добавляем поле для описания атрибута
            if (!Schema::hasColumn('attributes', 'description')) {
                $table->text('description')->nullable()->after('is_required');
            }
            
            // Добавляем поле для единицы измерения (для числовых атрибутов)
            if (!Schema::hasColumn('attributes', 'unit')) {
                $table->string('unit')->nullable()->after('description');
            }
            
            // Добавляем поле для минимального значения (для числовых атрибутов)
            if (!Schema::hasColumn('attributes', 'min_value')) {
                $table->decimal('min_value', 10, 2)->nullable()->after('unit');
            }
            
            // Добавляем поле для максимального значения (для числовых атрибутов)
            if (!Schema::hasColumn('attributes', 'max_value')) {
                $table->decimal('max_value', 10, 2)->nullable()->after('min_value');
            }
            
            // Добавляем поле для регулярного выражения валидации
            if (!Schema::hasColumn('attributes', 'validation_regex')) {
                $table->string('validation_regex')->nullable()->after('max_value');
            }
            
            // Добавляем поле для сортировки
            if (!Schema::hasColumn('attributes', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('validation_regex');
            }
            
            // Добавляем поле для активности атрибута
            if (!Schema::hasColumn('attributes', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('sort_order');
            }
            
            // Добавляем поле для способа отображения атрибута на фронтенде
            if (!Schema::hasColumn('attributes', 'display_type')) {
                $table->enum('display_type', [
                    'input',
                    'dropdown',
                    'radio',
                    'checkbox',
                    'color_swatch',
                    'image_swatch',
                    'toggle'
                ])->default('input')->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $columns = [
                'type',
                'input_type',
                'is_required', 
                'description',
                'unit',
                'min_value',
                'max_value',
                'validation_regex',
                'sort_order',
                'is_active',
                'display_type'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('attributes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
