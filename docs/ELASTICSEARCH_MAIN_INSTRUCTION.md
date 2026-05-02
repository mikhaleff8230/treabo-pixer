# 🔍 Elasticsearch - Главная инструкция (что сделано и что нужно доделать)

## ✅ Что уже работает

### 1. Автоматическая индексация при изменениях ✅

**Файл:** `pixer-api/packages/marvel/src/Observers/ProductElasticsearchObserver.php`

**Работает автоматически:**
- ✅ При создании товара → автоматически индексируется
- ✅ При обновлении товара → автоматически обновляется в индексе
- ✅ При удалении товара → автоматически удаляется из индекса

**Регистрация:** В `ShopServiceProvider.php` метод `registerElasticsearchObservers()`

---

### 2. Поиск по товарам ✅

**Работает:**
- ✅ Основной поиск: `GET /api/search?q=query&type=products`
- ✅ Фильтрация по категориям, ценам, наличию
- ✅ Сортировка по релевантности, цене, дате

---

## ❌ Что нужно доделать

### 1. Подсказки (autocomplete/suggestions) не работают

**Проблема:** Индекс может быть создан без поля `name.autocomplete`

**Решение:**

```bash
cd /var/www/sancan.ru/pixer-api

# 1. Пересоздать индекс с правильным маппингом (включая autocomplete)
php artisan elasticsearch:setup --recreate --index=products

# 2. Переиндексировать все товары
php artisan elasticsearch:index-products --fresh

# 3. Проверить что поле autocomplete есть
curl http://localhost:9200/sancan_products/_mapping | jq '.sancan_products.mappings.properties.name.fields.autocomplete'

# Должно показать настройки autocomplete поля
```

**Проверка работы:**

```bash
# Тест autocomplete (должен вернуть массив строк)
curl "https://api.sancan.ru/api/search/autocomplete?q=те&type=products"

# Тест suggestions (должен вернуть объект с products, tags, categories)
curl "https://api.sancan.ru/api/search/suggestions?q=тест&type=products"
```

---

### 2. Ежедневная автоматическая индексация

**Статус:** ✅ **ДОБАВЛЕНО** в `Kernel.php`

**Файл:** `pixer-api/app/Console/Kernel.php`

**Что добавлено:**
```php
// Ежедневная переиндексация товаров в Elasticsearch в 3:00 ночи
$schedule->command('elasticsearch:index-products --status=publish')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/elasticsearch-indexing.log'));
```

**Проверка:**

```bash
# Проверить что расписание добавлено
php artisan schedule:list | grep elasticsearch

# Должно показать:
# 0 3 * * *  elasticsearch:index-products --status=publish
```

**Важно:** Убедитесь что cron настроен:

```bash
# Проверить
crontab -l | grep schedule:run

# Если нет, добавить:
crontab -e
# Добавить строку:
* * * * * cd /var/www/sancan.ru/pixer-api && php artisan schedule:run >> /dev/null 2>&1
```

---

### 3. Автозапуск Elasticsearch при перезагрузке

**Статус:** ❌ Нужно включить

**Решение:**

```bash
# Включить автозапуск
sudo systemctl enable elasticsearch

# Проверить
sudo systemctl is-enabled elasticsearch
# Должно показать: enabled

# Проверить статус
sudo systemctl status elasticsearch
```

---

## 📊 Теги и категории

**Статус:** ✅ **ЕСТЬ в коде**, но могут не работать в подсказках

**Что есть:**
- ✅ Теги и категории индексируются вместе с товарами (в nested полях)
- ✅ Маппинг настроен правильно
- ✅ Код для извлечения тегов/категорий в suggestions есть

**Почему могут не работать:**
- Индекс был создан без правильного маппинга nested полей
- Нужно пересоздать индекс

**Решение:**

```bash
# Пересоздать индекс с правильным маппингом (включая nested поля для тегов и категорий)
php artisan elasticsearch:setup --recreate --index=products

# Переиндексировать
php artisan elasticsearch:index-products --fresh
```

**После этого теги и категории будут работать в подсказках!**

---

## 🎯 Что делать сейчас

### Шаг 1: Включить подсказки

```bash
cd /var/www/sancan.ru/pixer-api

# Пересоздать индекс с autocomplete полем
php artisan elasticsearch:setup --recreate --index=products

# Переиндексировать товары
php artisan elasticsearch:index-products --fresh

# Проверить
curl "https://api.sancan.ru/api/search/autocomplete?q=те&type=products"
```

### Шаг 2: Проверить ежедневную индексацию

```bash
# Проверить что добавлено в расписание
php artisan schedule:list | grep elasticsearch

# Проверить cron
crontab -l | grep schedule:run
```

### Шаг 3: Включить автозапуск Elasticsearch

```bash
sudo systemctl enable elasticsearch
sudo systemctl is-enabled elasticsearch
```

### Шаг 4: Тестирование

```bash
# Тест поиска
curl "https://api.sancan.ru/api/search?q=тест&type=products"

# Тест autocomplete
curl "https://api.sancan.ru/api/search/autocomplete?q=те&type=products"

# Тест suggestions (должны быть теги и категории)
curl "https://api.sancan.ru/api/search/suggestions?q=тест&type=products"
```

---

## 📁 Структура (что сделано)

### ✅ Автоматическая индексация (Observer)

**Файлы:**
- `pixer-api/packages/marvel/src/Observers/ProductElasticsearchObserver.php`
- `pixer-api/packages/marvel/src/Observers/PlaceElasticsearchObserver.php`

**Регистрация:** `pixer-api/packages/marvel/src/ShopServiceProvider.php`

**Работает:** ✅ Автоматически при создании/обновлении/удалении товаров

---

### ✅ Ежедневная индексация

**Файл:** `pixer-api/app/Console/Kernel.php`

**Добавлено:** ✅ Команда в `schedule()` для ежедневной индексации в 3:00

**Требует:** Настроенный cron для `schedule:run`

---

### ✅ Подсказки (код готов)

**Файл:** `pixer-api/packages/marvel/src/Http/Controllers/SearchController.php`

**Endpoints:**
- `GET /api/search/autocomplete?q=query` - горизонтальные подсказки
- `GET /api/search/suggestions?q=query` - вертикальные подсказки (товары, теги, категории)

**Требует:** Пересоздать индекс с полем `name.autocomplete`

---

## 🔍 Диагностика

### Проверить текущее состояние:

```bash
# Полная проверка
php artisan elasticsearch:verify --detailed

# Статус
php artisan elasticsearch:status

# Диагностика подключения
php artisan elasticsearch:diagnose
```

### Проверить маппинг:

```bash
# Проверить есть ли поле autocomplete
curl http://localhost:9200/sancan_products/_mapping | jq '.sancan_products.mappings.properties.name.fields.autocomplete'

# Если null - нужно пересоздать индекс
```

---

## ✅ Итоговый чек-лист

- [x] Автоматическая индексация при изменениях (Observer) - ✅ Работает
- [x] Ежедневная индексация добавлена в Kernel.php - ✅ Добавлено
- [ ] Подсказки работают - ❌ Нужно пересоздать индекс
- [ ] Автозапуск Elasticsearch - ❌ Нужно включить
- [ ] Cron для schedule:run - ❌ Нужно проверить/добавить

---

## 🚀 Быстрый старт (что сделать прямо сейчас)

```bash
cd /var/www/sancan.ru/pixer-api

# 1. Пересоздать индекс для подсказок
php artisan elasticsearch:setup --recreate --index=products
php artisan elasticsearch:index-products --fresh

# 2. Включить автозапуск Elasticsearch
sudo systemctl enable elasticsearch

# 3. Проверить cron
crontab -l | grep schedule:run
# Если нет, добавить:
crontab -e
# * * * * * cd /var/www/sancan.ru/pixer-api && php artisan schedule:run >> /dev/null 2>&1

# 4. Проверить результат
php artisan elasticsearch:verify
curl "https://api.sancan.ru/api/search/autocomplete?q=те&type=products"
```

---

**Главная инструкция:** `ELASTICSEARCH_COMPLETE_SETUP.md`  
**Дата:** 2025-01-13

