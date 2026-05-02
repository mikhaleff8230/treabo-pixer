# Настройка DaData.ru для геолокации

## Данные для .env

Добавьте в `.env` файл:

```env
# DaData API (основной сервис для геолокации)
DADATA_API_KEY=d801e135d101948fff6d21f35c5b5fc86581e067
DADATA_SECRET_KEY=29ff42382494ff4ed78f943bb05102d5bb43b4c6
```

## Новая гибридная система

### Приоритет сервисов:

1. **DaData.ru** - основной сервис
   - Определение города по IP
   - Поиск городов (автодополнение)
   - Валидация адресов
   - Обратный геокодинг (координаты → адрес)

2. **MaxMind** - fallback
   - Базовая геолокация по IP
   - Используется, если DaData недоступен

3. **Yandex** - опционально (отключен по умолчанию)
   - Код сохранен для будущего использования
   - Можно включить через `config('services.yandex_locator.enabled', false)`

## Что реализовано

### 1. Определение по IP через DaData
- Метод: `getDaDataLocationByIp()`
- API: `https://suggestions.dadata.ru/suggestions/api/4_1/rs/iplocate/address`
- Возвращает: город, регион, координаты, КЛАДР, ФИАС

### 2. Поиск городов через DaData
- Метод: `searchCities()`
- API: `https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address`
- Фильтр: только города
- Используется для автодополнения

### 3. Обратный геокодинг через DaData
- Метод: `getDaDataLocationByCoordinates()`
- Координаты → адрес
- Используется для HTML5 Geolocation

## После настройки

1. Добавьте ключи в `.env`
2. Очистите кэш:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```
3. Проверьте работу:
   ```
   https://api.sancan.ru/debug/geolocation-test
   ```

## Код Yandex

Код Yandex сохранен, но отключен. Для включения:
- Установите `YANDEX_GEO_API_KEY` в `.env`
- Включите в конфиге: `'enabled' => true` для `yandex_locator`

