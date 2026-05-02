# 🔍 Elasticsearch - Руководство по использованию

## 📚 Содержание

1. [Установка и настройка](#установка-и-настройка)
2. [Команды Artisan](#команды-artisan)
3. [API Endpoints](#api-endpoints)
4. [Примеры использования](#примеры-использования)
5. [Аналитика поиска](#аналитика-поиска)
6. [Оптимизация](#оптимизация)
7. [Troubleshooting](#troubleshooting)

---

## 🚀 Установка и настройка

### 1. Установка Elasticsearch на сервере

```bash
# На сервере выполните:
wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch | sudo gpg --dearmor -o /usr/share/keyrings/elasticsearch-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/elasticsearch-keyring.gpg] https://artifacts.elastic.co/packages/8.x/apt stable main" | sudo tee /etc/apt/sources.list.d/elastic-8.x.list
sudo apt-get update
sudo apt-get install elasticsearch

# Запуск и автозагрузка
sudo systemctl enable elasticsearch
sudo systemctl start elasticsearch
```

### 2. Установка PHP пакетов

```bash
cd /var/www/sancan.ru/pixer-api
composer require elasticsearch/elasticsearch:^8.0
composer require laravel/scout
composer require matchish/laravel-scout-elasticsearch
```

### 3. Конфигурация .env

```env
# Elasticsearch Configuration
ELASTICSEARCH_ENABLED=true
ELASTICSEARCH_HOST=localhost:9200
ELASTICSEARCH_USER=laravel
ELASTICSEARCH_PASSWORD=ваш_пароль
ELASTICSEARCH_SCHEME=http
ELASTICSEARCH_INDEX_PREFIX=sancan_

# Queue (опционально, для фоновой индексации)
ELASTICSEARCH_QUEUE=true
QUEUE_CONNECTION=redis

# Logging
ELASTICSEARCH_LOGGING=true
ELASTICSEARCH_LOG_QUERIES=false

# Analytics
ELASTICSEARCH_ANALYTICS=true
```

### 4. Запуск миграций

```bash
php artisan migrate
```

---

## 🛠️ Команды Artisan

### Настройка индексов

```bash
# Создать все индексы
php artisan elasticsearch:setup

# Пересоздать все индексы (удалить и создать заново)
php artisan elasticsearch:setup --recreate

# Создать только конкретный индекс
php artisan elasticsearch:setup --index=products
```

### Индексация данных

```bash
# Индексировать все товары
php artisan elasticsearch:index-products

# Индексировать товары с пересозданием индекса
php artisan elasticsearch:index-products --fresh

# Индексировать только опубликованные товары
php artisan elasticsearch:index-products --status=publish

# Изменить размер batch (по умолчанию 500)
php artisan elasticsearch:index-products --chunk=1000

# Индексировать плейсы
php artisan elasticsearch:index-places

# Индексировать плейсы с пересозданием индекса
php artisan elasticsearch:index-places --fresh
```

### Проверка статуса

```bash
# Показать статус кластера и индексов
php artisan elasticsearch:status

# Показать детальную статистику
php artisan elasticsearch:status --detailed
```

---

## 🌐 API Endpoints

### 1. Основной поиск

**Endpoint:** `GET /api/search`

**Параметры:**
- `q` (обязательно) - поисковый запрос
- `type` (обязательно) - тип: `products`, `places`, `categories`, `shops`
- `page` (опционально) - номер страницы (по умолчанию: 1)
- `per_page` (опционально) - результатов на странице (по умолчанию: 20, макс: 100)
- `sort` (опционально) - сортировка: `relevance`, `price_asc`, `price_desc`, `newest`, `popular`, `rating`
- `filters` (опционально) - фильтры (JSON)

**Примеры:**

```bash
# Простой поиск товаров
curl "https://api.sancan.ru/search?q=телефон&type=products"

# Поиск с пагинацией
curl "https://api.sancan.ru/search?q=телефон&type=products&page=2&per_page=30"

# Поиск с сортировкой по цене
curl "https://api.sancan.ru/search?q=телефон&type=products&sort=price_asc"

# Поиск с фильтрами
curl -G "https://api.sancan.ru/search" \
  --data-urlencode "q=телефон" \
  --data-urlencode "type=products" \
  --data-urlencode 'filters[in_stock]=true' \
  --data-urlencode 'filters[price_min]=1000' \
  --data-urlencode 'filters[price_max]=50000'

# Поиск плейсов
curl "https://api.sancan.ru/search?q=путешествие&type=places"
```

**Ответ:**

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 123,
        "name": "iPhone 15 Pro",
        "slug": "iphone-15-pro",
        "description": "Новейший флагман от Apple",
        "price": 89990,
        "sale_price": 79990,
        "in_stock": true,
        "shop": {
          "id": 1,
          "name": "Apple Store",
          "slug": "apple-store"
        },
        "categories": [
          {"id": 5, "name": "Смартфоны", "slug": "smartphones"}
        ],
        "ratings": 4.8,
        "total_reviews": 152,
        "score": 15.234
      }
    ],
    "aggregations": {
      "categories": [
        {"key": 5, "doc_count": 234},
        {"key": 12, "doc_count": 89}
      ],
      "price_ranges": [
        {"key": "0-1000", "doc_count": 45},
        {"key": "1000-5000", "doc_count": 123}
      ]
    }
  },
  "pagination": {
    "total": 1245,
    "per_page": 20,
    "current_page": 1,
    "last_page": 63
  },
  "query": "телефон",
  "type": "products"
}
```

### 2. Автокомплит

**Endpoint:** `GET /api/search/autocomplete`

**Параметры:**
- `q` (обязательно) - поисковый запрос (минимум 2 символа)
- `type` (опционально) - `products` или `places` (по умолчанию: `products`)
- `limit` (опционально) - количество подсказок (по умолчанию: 10, макс: 20)

**Примеры:**

```bash
# Автокомплит для товаров
curl "https://api.sancan.ru/search/autocomplete?q=теле&type=products"

# Автокомплит для плейсов
curl "https://api.sancan.ru/search/autocomplete?q=пут&type=places"
```

**Ответ:**

```json
{
  "success": true,
  "suggestions": [
    {"id": 123, "text": "Телефон iPhone 15", "type": "products"},
    {"id": 456, "text": "Телевизор Samsung", "type": "products"},
    {"id": 789, "text": "Телевизор LG", "type": "products"}
  ]
}
```

### 3. Подсказки "Возможно вы искали..."

**Endpoint:** `GET /api/search/suggestions`

**Параметры:**
- `q` (обязательно) - поисковый запрос

**Примеры:**

```bash
curl "https://api.sancan.ru/search/suggestions?q=телефон"
```

### 4. Трекинг кликов (аналитика)

**Endpoint:** `POST /api/search/track-click`

**Параметры:**
- `query` (обязательно) - поисковый запрос
- `result_id` (обязательно) - ID результата, по которому кликнули
- `result_type` (обязательно) - тип результата (`products`, `places`)
- `position` (опционально) - позиция в результатах

**Примеры:**

```bash
curl -X POST "https://api.sancan.ru/search/track-click" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "телефон",
    "result_id": 123,
    "result_type": "products",
    "position": 3
  }'
```

---

## 💡 Примеры использования

### Frontend (JavaScript/React)

#### Простой поиск

```javascript
// Поиск товаров
const searchProducts = async (query) => {
  const response = await fetch(
    `https://api.sancan.ru/search?q=${encodeURIComponent(query)}&type=products`
  );
  const data = await response.json();
  return data;
};

// Использование
const results = await searchProducts('телефон');
console.log(results.data.items);
```

#### Автокомплит

```javascript
const autocomplete = async (query) => {
  if (query.length < 2) return [];
  
  const response = await fetch(
    `https://api.sancan.ru/search/autocomplete?q=${encodeURIComponent(query)}&type=products`
  );
  const data = await response.json();
  return data.suggestions;
};

// Использование с debounce
import { debounce } from 'lodash';

const handleSearchInput = debounce(async (value) => {
  const suggestions = await autocomplete(value);
  setSuggestions(suggestions);
}, 300);
```

#### Фильтрованный поиск

```javascript
const searchWithFilters = async (query, filters) => {
  const params = new URLSearchParams({
    q: query,
    type: 'products',
    sort: filters.sort || 'relevance',
    page: filters.page || 1,
    per_page: filters.perPage || 20,
  });

  // Добавляем фильтры
  if (filters.inStock) {
    params.append('filters[in_stock]', 'true');
  }
  if (filters.priceMin) {
    params.append('filters[price_min]', filters.priceMin);
  }
  if (filters.priceMax) {
    params.append('filters[price_max]', filters.priceMax);
  }
  if (filters.categories && filters.categories.length > 0) {
    filters.categories.forEach(cat => {
      params.append('filters[categories.id][]', cat);
    });
  }

  const response = await fetch(`https://api.sancan.ru/search?${params}`);
  return await response.json();
};

// Использование
const results = await searchWithFilters('телефон', {
  sort: 'price_asc',
  inStock: true,
  priceMin: 1000,
  priceMax: 50000,
  categories: [5, 12],
  page: 1,
  perPage: 30,
});
```

#### Трекинг кликов

```javascript
const trackClick = async (query, resultId, resultType, position) => {
  await fetch('https://api.sancan.ru/search/track-click', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      query,
      result_id: resultId,
      result_type: resultType,
      position,
    }),
  });
};

// Использование
const handleProductClick = (product, position) => {
  trackClick('телефон', product.id, 'products', position);
  // Переход на страницу товара
  window.location.href = `/products/${product.slug}`;
};
```

### React компонент для поиска

```jsx
import React, { useState, useCallback } from 'react';
import { debounce } from 'lodash';

const SearchBox = () => {
  const [query, setQuery] = useState('');
  const [suggestions, setSuggestions] = useState([]);
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(false);

  // Автокомплит
  const fetchSuggestions = useCallback(
    debounce(async (value) => {
      if (value.length < 2) {
        setSuggestions([]);
        return;
      }
      
      const response = await fetch(
        `https://api.sancan.ru/search/autocomplete?q=${encodeURIComponent(value)}`
      );
      const data = await response.json();
      setSuggestions(data.suggestions || []);
    }, 300),
    []
  );

  // Поиск
  const handleSearch = async (searchQuery) => {
    setLoading(true);
    try {
      const response = await fetch(
        `https://api.sancan.ru/search?q=${encodeURIComponent(searchQuery)}&type=products`
      );
      const data = await response.json();
      setResults(data.data.items || []);
    } catch (error) {
      console.error('Search error:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleInputChange = (e) => {
    const value = e.target.value;
    setQuery(value);
    fetchSuggestions(value);
  };

  const handleSuggestionClick = (suggestion) => {
    setQuery(suggestion.text);
    setSuggestions([]);
    handleSearch(suggestion.text);
  };

  return (
    <div className="search-box">
      <input
        type="text"
        value={query}
        onChange={handleInputChange}
        onKeyPress={(e) => e.key === 'Enter' && handleSearch(query)}
        placeholder="Поиск товаров..."
      />
      
      {suggestions.length > 0 && (
        <ul className="suggestions">
          {suggestions.map((suggestion) => (
            <li
              key={suggestion.id}
              onClick={() => handleSuggestionClick(suggestion)}
            >
              {suggestion.text}
            </li>
          ))}
        </ul>
      )}

      {loading && <div>Загрузка...</div>}

      <div className="results">
        {results.map((product, index) => (
          <div
            key={product.id}
            onClick={() => {
              // Трекинг клика
              fetch('https://api.sancan.ru/search/track-click', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  query,
                  result_id: product.id,
                  result_type: 'products',
                  position: index + 1,
                }),
              });
              // Переход на страницу товара
              window.location.href = `/products/${product.slug}`;
            }}
          >
            <h3>{product.name}</h3>
            <p>{product.price} ₽</p>
          </div>
        ))}
      </div>
    </div>
  );
};

export default SearchBox;
```

---

## 📊 Аналитика поиска

### Популярные запросы

```sql
-- Топ 10 популярных запросов за последние 7 дней
SELECT 
  query,
  COUNT(*) as search_count,
  SUM(CASE WHEN result_id IS NOT NULL THEN 1 ELSE 0 END) as clicks
FROM search_logs
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
  AND result_id IS NULL  -- Только поисковые запросы, без кликов
GROUP BY query
ORDER BY search_count DESC
LIMIT 10;
```

### Запросы без результатов

```sql
-- Запросы, которые не дали результатов
SELECT query, COUNT(*) as count
FROM search_logs
WHERE results_count = 0
  AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY query
ORDER BY count DESC
LIMIT 20;
```

### CTR (Click Through Rate)

```sql
-- CTR по популярным запросам
SELECT 
  query,
  COUNT(DISTINCT CASE WHEN result_id IS NULL THEN id END) as searches,
  COUNT(DISTINCT CASE WHEN result_id IS NOT NULL THEN id END) as clicks,
  ROUND(
    COUNT(DISTINCT CASE WHEN result_id IS NOT NULL THEN id END) * 100.0 / 
    NULLIF(COUNT(DISTINCT CASE WHEN result_id IS NULL THEN id END), 0),
    2
  ) as ctr_percent
FROM search_logs
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY query
HAVING searches > 10
ORDER BY searches DESC
LIMIT 20;
```

### Популярные товары в поиске

```sql
-- Товары, на которые чаще всего кликают из поиска
SELECT 
  result_id as product_id,
  result_type,
  COUNT(*) as click_count,
  COUNT(DISTINCT query) as unique_queries
FROM search_logs
WHERE result_id IS NOT NULL
  AND result_type = 'products'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY result_id, result_type
ORDER BY click_count DESC
LIMIT 20;
```

---

## ⚡ Оптимизация

### 1. Настройка памяти Elasticsearch

```bash
# Редактируем heap size (50% от RAM, но не более 32GB)
sudo nano /etc/elasticsearch/jvm.options.d/heap.options

# Для сервера с 8GB RAM:
-Xms4g
-Xmx4g
```

### 2. Оптимизация индексов

```bash
# На сервере Elasticsearch выполните:

# Форсированный merge сегментов (оптимизация)
curl -X POST "localhost:9200/sancan_products/_forcemerge?max_num_segments=1"

# Обновление настроек индекса
curl -X PUT "localhost:9200/sancan_products/_settings" -H 'Content-Type: application/json' -d'
{
  "index": {
    "refresh_interval": "30s",
    "number_of_replicas": 1
  }
}'
```

### 3. Кэширование результатов поиска

В Laravel можно добавить кэширование популярных запросов:

```php
// В SearchController.php
public function search(Request $request): JsonResponse
{
    $query = $request->input('q');
    $cacheKey = "search:{$query}:{$request->input('type')}";
    
    $results = Cache::remember($cacheKey, 300, function() use ($request, $query) {
        // ... выполнение поиска
        return $this->elasticsearch->search(...);
    });
    
    // ... возврат результатов
}
```

### 4. Фоновая индексация

```env
# В .env включите очереди
ELASTICSEARCH_QUEUE=true
QUEUE_CONNECTION=redis

# Запустите воркер
php artisan queue:work --queue=elasticsearch
```

---

## 🔧 Troubleshooting

### Elasticsearch не запускается

```bash
# Проверьте статус
sudo systemctl status elasticsearch

# Проверьте логи
sudo tail -f /var/log/elasticsearch/sancan-production.log

# Проверьте heap size
sudo cat /etc/elasticsearch/jvm.options.d/heap.options

# Проверьте права доступа
sudo chown -R elasticsearch:elasticsearch /var/lib/elasticsearch
sudo chown -R elasticsearch:elasticsearch /var/log/elasticsearch
```

### Индексы не создаются

```bash
# Проверьте подключение
curl http://localhost:9200

# Проверьте здоровье кластера
curl http://localhost:9200/_cluster/health?pretty

# Проверьте настройки в Laravel
php artisan tinker
>>> config('elasticsearch')
```

### Медленный поиск

```bash
# Проверьте статистику
curl http://localhost:9200/_cat/indices?v

# Проверьте количество сегментов
curl http://localhost:9200/_cat/segments/sancan_products?v

# Если сегментов много, выполните merge
curl -X POST "localhost:9200/sancan_products/_forcemerge?max_num_segments=5"
```

### Результаты поиска не обновляются

```bash
# Обновите индекс
php artisan elasticsearch:index-products --fresh

# Проверьте работу Observers
# В AppServiceProvider.php должна быть регистрация:
Product::observe(ProductObserver::class);
```

---

## 📝 Полезные команды

```bash
# Проверка кластера
curl http://localhost:9200/_cluster/health?pretty

# Список индексов
curl http://localhost:9200/_cat/indices?v

# Статистика индекса
curl http://localhost:9200/sancan_products/_stats?pretty

# Количество документов
curl http://localhost:9200/sancan_products/_count

# Удалить индекс
curl -X DELETE http://localhost:9200/sancan_products

# Обновить mapping (требует пересоздания индекса)
php artisan elasticsearch:setup --recreate --index=products

# Тестовый поиск
curl -X GET "localhost:9200/sancan_products/_search?pretty" -H 'Content-Type: application/json' -d'
{
  "query": {
    "match": {
      "name": "телефон"
    }
  }
}'
```

---

## 🎯 Best Practices

1. **Регулярная индексация:** Настройте cron для периодической переиндексации
2. **Мониторинг:** Следите за использованием памяти и CPU Elasticsearch
3. **Резервное копирование:** Настройте snapshot репозиторий
4. **Анализ логов:** Регулярно анализируйте search_logs для улучшения релевантности
5. **A/B тестирование:** Тестируйте различные настройки boosting и ranking
6. **Оптимизация запросов:** Используйте фильтры вместо queries где возможно
7. **Кэширование:** Кэшируйте популярные запросы на уровне приложения

---

## 📞 Поддержка

При возникновении проблем:
1. Проверьте логи Laravel: `storage/logs/laravel.log`
2. Проверьте логи Elasticsearch: `/var/log/elasticsearch/`
3. Запустите диагностику: `php artisan elasticsearch:status --detailed`
4. Проверьте конфигурацию: `config/elasticsearch.php`

---

**Версия документации:** 1.0  
**Дата обновления:** {{ date('Y-m-d') }}


