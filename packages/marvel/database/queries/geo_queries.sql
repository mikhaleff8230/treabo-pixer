-- =============================================
-- ГЕОЛОКАЦИОННЫЕ ЗАПРОСЫ ДЛЯ ЛАРАВЕЛЬ API
-- =============================================

-- 1. Получить товары в регионе пользователя (основной запрос)
SELECT * FROM products p
WHERE p.region_id = :region_id 
  AND p.is_active = true 
  AND p.is_published = true
ORDER BY p.created_at DESC
LIMIT 50;

-- Laravel Query Builder эквивалент:
Product::published()
    ->where('region_id', $regionId)
    ->latest()
    ->paginate(20);

-- 2. Товары в регионе + соседних регионах (как на Авито)
SELECT p.* FROM products p
WHERE p.is_active = true 
  AND p.is_published = true
  AND (
    p.region_id = :region_id
    OR p.region_id IN (
      SELECT neighbor_region_id 
      FROM region_neighbors 
      WHERE region_id = :region_id
    )
  )
ORDER BY p.created_at DESC;

-- Laravel:
Product::inRegionOrNeighbors($userRegionId)
    ->with(['region', 'geoPoint'])
    ->latest()
    ->paginate(24);

-- 3. Поиск по радиусу (PostGIS)
SELECT p.*, ST_Distance(
    g.location, 
    ST_MakePoint(:lng, :lat)::geography
) as distance_meters
FROM products p
JOIN geo_points g ON g.id = p.geo_point_id
WHERE ST_DWithin(
    g.location,
    ST_MakePoint(:lng, :lat)::geography,
    :radius_meters
)
AND p.is_active = true 
AND p.is_published = true
ORDER BY distance_meters ASC;

-- Laravel (с PostGIS):
DB::table('products as p')
    ->join('geo_points as g', 'g.id', '=', 'p.geo_point_id')
    ->whereRaw('ST_DWithin(g.location, ST_MakePoint(?, ?)::geography, ?)', [$lng, $lat, $radius])
    ->where('p.is_active', true)
    ->where('p.is_published', true)
    ->orderByRaw('ST_Distance(g.location, ST_MakePoint(?, ?)::geography)', [$lng, $lat])
    ->select('p.*', DB::raw('ST_Distance(...) as distance'))
    ->paginate(20);

-- 4. Создание региона (пример)
INSERT INTO regions (parent_id, type, name, slug, is_active, created_at, updated_at)
VALUES (NULL, 'country', 'Россия', 'russia', true, NOW(), NOW());

-- 5. Привязка продукта к городу + координатам
-- Сначала создаем geo_point, затем product с region_id = city_id и geo_point_id
