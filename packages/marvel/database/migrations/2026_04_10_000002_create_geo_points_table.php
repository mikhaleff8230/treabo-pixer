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
     * Таблица для хранения географических координат.
     * Если доступен PostGIS — используем тип geography(Point, 4326) для точных расчетов расстояний.
     * Иначе — обычные double precision + составной индекс.
     */
    public function up(): void
    {
        // Определяем, используется ли PostgreSQL с PostGIS
        $isPostgres = config('database.default') === 'pgsql' || 
                     DB::getDriverName() === 'pgsql';
        
        $hasPostgis = false;
        
        if ($isPostgres) {
            try {
                // Проверяем наличие расширения postgis
                $result = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'postgis'");
                $hasPostgis = !empty($result);
            } catch (\Exception $e) {
                $hasPostgis = false; // MySQL или PostGIS не установлен
            }
        }

        Schema::create('geo_points', function (Blueprint $table) use ($hasPostgis, $isPostgres) {
            $table->id();
            
            if ($hasPostgis && $isPostgres) {
                // PostGIS — рекомендуемый вариант (только для PostgreSQL)
                $table->addColumn('geometry', 'location', [
                    'type' => 'geography(Point,4326)',
                    'nullable' => false,
                ]);
                $table->comment('PostGIS geography point for accurate distance calculations');
            } else {
                $table->double('lat', 10, 7);
                $table->double('lng', 10, 7);
                $table->comment('Standard coordinates (MySQL or PostgreSQL without PostGIS)');
            }
            
            $table->timestamps();

            // Индексы
            if ($hasPostgis && $isPostgres) {
                // GIST индекс только если PostGIS доступен
                DB::statement('CREATE INDEX IF NOT EXISTS idx_geo_points_location_gist ON geo_points USING GIST (location);');
            } else {
                $table->index(['lat', 'lng'], 'idx_geo_points_coordinates');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_points');
    }
};
