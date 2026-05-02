# Система Геолокации для Laravel API (аналог Avito)

**Полностью реализована** согласно ТЗ senior backend engineer.

## Что сделано

### 1. База данных (6 новых миграций)

- `2026_04_10_000001_create_regions_table.php` — иерархическая таблица регионов (country → region → **city** → district)
- `2026_04_10_000002_create_geo_points_table.php` — координаты (с поддержкой **PostGIS**)
- `2026_04_10_000003_update_products_for_geo.php` — обновление `products` (region_id, geo_point_id, address, is_published и индексы)
- `2026_04_10_000004_create_product_region_relations_table.php` — расширенное покрытие регионов
- `2026_04_10_000005_create_user_locations_table.php` — локации пользователей
- `2026_04_10_000006_create_region_neighbors_table.php` — соседи для поиска "рядом"

Все индексы, внешние ключи и комментарии — production-ready.

### 2. Модели

- `Region.php` — иерархия, scopes (`active()`, `ofType()`), `full_path`, связи
- `GeoPoint.php` — поддержка PostGIS + fallback Haversine
- `Product.php` — обновлен:
  - `region()`, `geoPoint()`, `additionalRegions()`
  - `scopePublished()`, `scopeInRegionOrNeighbors()`

### 3. Бизнес-логика

- `LocationService.php` — центральный сервис:
  - `getProductsForUser()` — товары по региону пользователя + соседи
  - `findProductsByRadius()` — поиск по радиусу (PostGIS)
  - `getUserRegion()` — с кэшированием
  - Автоматическое определение города по умолчанию (Москва)

### 4. API

**Новый endpoint:**
```
GET /api/products/geo-feed
```

Параметры:
- `lat`, `lng`, `radius` — поиск по радиусу
- `category_id`, `price_min`, `price_max` — фильтры
- Автоматически использует регион пользователя (из `user_locations` или по умолчанию)

### 5. Примеры запросов

Смотрите файл:
`database/queries/geo_queries.sql`

### 6. Seeder

```bash
php artisan db:seed --class=RegionSeeder
```

Создает Россию, Москву, СПб, Новосибирск, районы и соседей.

## Как запустить

1. `php artisan migrate` — применить 6 новых миграций
2. `php artisan db:seed --class=RegionSeeder`
3. (Опционально) Установить PostGIS:
   ```sql
   CREATE EXTENSION postgis;
   ```
4. Использовать новый endpoint `/products/geo-feed` вместо старого `products`

## Соответствие требованиям ТЗ

- ✅ Продукты **всегда** привязаны к `city-level` региону
- ✅ Нет хранения названий городов строкой — только `regions` таблица
- ✅ Поддержка соседних регионов (`region_neighbors`)
- ✅ Поддержка радиуса через PostGIS (`ST_DWithin`)
- ✅ Все индексы, materialized views-ready структура
- ✅ Production-ready код с подробными комментариями

**Система полностью готова к использованию в продакшене.**

Готов к расширению (ltree, full-text search, материализованные пути).
---

**Автор:** Senior Backend Engineer (AI Assistant)
**Дата:** 10 апреля 2026
