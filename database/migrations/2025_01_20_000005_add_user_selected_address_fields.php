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
        Schema::table('user_addresses', function (Blueprint $table) {
            // Добавляем поля для полного адреса (выбранного пользователем)
            // Проверяем существование полей перед добавлением (идемпотентность)
            if (!Schema::hasColumn('user_addresses', 'region')) {
                $table->string('region')->nullable()->after('city');
            }
            if (!Schema::hasColumn('user_addresses', 'region_with_type')) {
                $table->string('region_with_type')->nullable()->after('region');
            }
            if (!Schema::hasColumn('user_addresses', 'country')) {
                $table->string('country')->nullable()->after('region_with_type');
            }
            if (!Schema::hasColumn('user_addresses', 'street')) {
                $table->string('street')->nullable()->after('address');
            }
            if (!Schema::hasColumn('user_addresses', 'street_with_type')) {
                $table->string('street_with_type')->nullable()->after('street');
            }
            if (!Schema::hasColumn('user_addresses', 'house')) {
                $table->string('house')->nullable()->after('street_with_type');
            }
            if (!Schema::hasColumn('user_addresses', 'flat')) {
                $table->string('flat')->nullable()->after('house');
            }
            if (!Schema::hasColumn('user_addresses', 'postal_code')) {
                $table->string('postal_code')->nullable()->after('flat');
            }
            if (!Schema::hasColumn('user_addresses', 'kladr_id')) {
                $table->string('kladr_id')->nullable()->after('postal_code');
            }
            if (!Schema::hasColumn('user_addresses', 'fias_id')) {
                $table->string('fias_id')->nullable()->after('kladr_id');
            }
            if (!Schema::hasColumn('user_addresses', 'full_address')) {
                $table->text('full_address')->nullable()->after('fias_id'); // Полный адрес одной строкой
            }
            
            // Добавляем поле для источника адреса (auto_detected, user_selected)
            if (!Schema::hasColumn('user_addresses', 'source')) {
                $table->string('source')->default('user_selected')->after('type'); // 'auto_detected' или 'user_selected'
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_addresses', function (Blueprint $table) {
            $table->dropColumn([
                'region',
                'region_with_type',
                'country',
                'street',
                'street_with_type',
                'house',
                'flat',
                'postal_code',
                'kladr_id',
                'fias_id',
                'full_address',
                'source'
            ]);
        });
    }
};

