# Система кэширования геолокации по IP для экономии лимита DaData

## Проблема

DaData предоставляет бесплатный лимит **10 000 запросов в день** для определения города по IP. При простом подключении API на сервере можно быстро исчерпать лимит из-за:

1. **Повторные запросы** - когда пользователь ходит по страницам сайта, каждая страница заново пытается определить город
2. **Поисковые боты** - Яндекс, Google, Bing генерируют большое количество запросов

## Решение

Реализована система кэширования результатов геолокации в базе данных с проверкой на ботов.

### 1. Таблица `geo_ip_cache`

Создана таблица для постоянного хранения результатов геолокации по IP-адресам:

```sql
CREATE TABLE geo_ip_cache (
    id BIGINT PRIMARY KEY,
    ip_address VARCHAR(45) UNIQUE,  -- IPv4 или IPv6
    city VARCHAR(255),
    region VARCHAR(255),
    country VARCHAR(100),
    lat DECIMAL(10,8),
    lon DECIMAL(11,8),
    source VARCHAR(50),  -- dadata, maxmind, yandex
    request_count INT DEFAULT 1,  -- Количество использований
    last_used_at TIMESTAMP,  -- Последнее использование
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    -- ... другие поля
);
```

### 2. Логика работы

#### Шаг 1: Проверка БД
Перед запросом к DaData проверяем, есть ли уже сохраненный результат для этого IP в базе данных.

```php
$cachedLocation = GeoIpCache::findByIp($ip);
if ($cachedLocation) {
    // Увеличиваем счетчик использования
    $cachedLocation->incrementUsage();
    return $cachedLocation->toLocationArray();
}
```

**Результат:** Если IP уже был определен ранее, возвращаем результат из БД без запроса к DaData.

#### Шаг 2: Проверка на бота
Перед запросом к DaData проверяем, является ли запрос от бота.

```php
$isBot = $this->isBotRequest();
if ($isBot) {
    // Для ботов используем только бесплатный MaxMind
    $maxmindLocation = $this->getMaxMindLocation($ip);
    GeoIpCache::saveLocation($ip, $maxmindLocation);
    return $maxmindLocation;
}
```

**Результат:** Для ботов (Google, Yandex, Bing и др.) используем только бесплатный MaxMind, не тратя лимит DaData.

#### Шаг 3: Запрос к DaData
Только для реальных пользователей делаем запрос к DaData.

```php
$dadataLocation = $this->getDaDataLocationByIp($ip);
if ($dadataLocation) {
    // Сохраняем результат в БД для будущих запросов
    GeoIpCache::saveLocation($ip, $dadataLocation);
    return $dadataLocation;
}
```

**Результат:** Результат сохраняется в БД, чтобы при следующем запросе с этого IP не делать повторный запрос к DaData.

### 3. Дополнительное кэширование в Redis

Помимо БД, используется кэширование в Redis на 1 час для еще более быстрого доступа:

```php
$cacheKey = "geo_location_{$ip}_" . md5(json_encode(['wifi' => $wifi, 'cell' => $cell]));
return Cache::remember($cacheKey, 3600, function () use ($ip) {
    // Запрос к DaData или MaxMind
});
```

## Преимущества

1. **Экономия лимита DaData** - повторные запросы с одного IP не тратят лимит
2. **Защита от ботов** - боты используют только бесплатный MaxMind
3. **Быстрый доступ** - результаты из БД возвращаются мгновенно
4. **Статистика** - счетчик `request_count` показывает, сколько раз использовался IP
5. **Автоматическое обновление** - поле `last_used_at` обновляется при каждом использовании

## Установка

1. Выполните миграцию:
```bash
php artisan migrate
```

2. Проверьте, что таблица создана:
```bash
php artisan tinker
>>> Schema::hasTable('geo_ip_cache')
```

3. Проверьте работу:
```bash
# Первый запрос - будет запрос к DaData
curl https://api.sancan.ru/api/geoip/location

# Второй запрос с того же IP - будет из БД
curl https://api.sancan.ru/api/geoip/location
```

## Мониторинг

### Статистика использования кэша

```sql
-- Топ IP по количеству использований
SELECT ip_address, city, country, request_count, last_used_at
FROM geo_ip_cache
ORDER BY request_count DESC
LIMIT 10;

-- Количество записей в кэше
SELECT COUNT(*) as total FROM geo_ip_cache;

-- Количество записей по источникам
SELECT source, COUNT(*) as count
FROM geo_ip_cache
GROUP BY source;
```

### Очистка старых записей

Можно настроить автоматическую очистку записей, которые не использовались более 30 дней:

```php
// В App\Console\Kernel.php
$schedule->command('geoip:cleanup')->daily();
```

## Рекомендации DaData

Система реализует обе рекомендации DaData:

1. ✅ **"Запоминать результат"** - результаты сохраняются в БД
2. ✅ **"Не делать повторных вызовов"** - проверка БД перед запросом к DaData

## Ограничения

- Точность определения города по IP: **60-80%** (согласно документации DaData)
- Если DaData не смогла определить город (`location = null`), используется MaxMind
- Для ботов всегда используется MaxMind (бесплатный)

## Дополнительная информация

- Документация DaData: https://dadata.ru/api/iplocate/
- Модель: `App\Models\GeoIpCache`
- Сервис: `App\Services\GeoLocationService`

