# Places System Architecture & Development Guide

## 📋 Обзор

Система Places была полностью переработана с page-based pagination на **cursor-based infinite scroll** архитектуру. Реализован паттерн **"Data Hook → Orchestrator → Pure UI"** для максимальной переиспользуемости и производительности.

## 🏗️ Архитектура

### Основной паттерн
```
Data Layer → Orchestrator → UI Layer
    ↓           ↓           ↓
useInfinitePlacesFeed → PlacesFeed → PlaceMasonryGrid
```

### Принципы
- **Разделение ответственности:** Каждый слой отвечает только за свою задачу
- **Cursor-based pagination:** Стабильная пагинация без смещения
- **Легковесные данные:** Только необходимые поля для фида
- **Pure components:** UI компоненты без бизнес-логики

## 🔧 Компоненты системы

### Backend (Laravel)

#### 1. PlaceFeedQuery
```php
// packages/marvel/src/Http/Queries/PlaceFeedQuery.php
class PlaceFeedQuery {
    public static function query(array $params = []): Builder
    // Создает оптимизированный запрос для фида
}
```

**Особенности:**
- Выбирает только легкие поля (id, title, slug, user_id, created_at)
- Cursor-based сортировка (created_at DESC, id DESC)
- Оптимизированные связи (только 1 изображение + пользователь)

#### 2. PlaceFeedResource
```php
// packages/marvel/src/Http/Resources/PlaceFeedResource.php
class PlaceFeedResource extends JsonResource {
    public function toArray($request)
    // Легковесный ресурс для фида
}
```

**Поля:**
- `id`, `title`, `slug`, `url`, `created_at`
- `preview_image` (первое изображение или null)
- `likes_count`, `comments_count`
- `user` (id, name, avatar)

#### 3. PlaceController
```php
// packages/marvel/src/Http/Controllers/PlaceController.php

// Cursor-based infinite scroll feed
public function feed(Request $request)

// Похожие плейсы (limit 12, без cursor)
public function similar(Request $request, $id)
```

### Frontend (Next.js/React)

#### 1. useInfinitePlacesFeed Hook
```typescript
// shop/src/data/place.ts
export const useInfinitePlacesFeed = (options) => {
    return useInfiniteQuery({
        queryKey: [API_ENDPOINTS.PLACES_FEED, limit],
        queryFn: async ({ pageParam }) => {
            // Cursor-based loading
        },
        getNextPageParam: (lastPage) => {
            return lastPage.meta?.has_more ? lastPage.meta?.next_cursor : undefined;
        }
    });
};
```

#### 2. PlacesFeed Component
```typescript
// shop/src/components/places/places-feed.tsx
export default function PlacesFeed({ limit, showLoadMore }) {
    const { data, isLoading, hasNextPage, fetchNextPage } = useInfinitePlacesFeed({ limit });

    const allPlaces = data?.pages.flatMap(page => page.data || []) || [];

    return (
        <PlaceMasonryGrid places={allPlaces} onLastItemRef={lastElementRef} />
    );
}
```

#### 3. PlaceMasonryGrid Component
```typescript
// shop/src/components/places/place-masonry-grid.tsx
export default function PlaceMasonryGrid({ places, onLastItemRef }) {
    // Чистое отображение без логики загрузки
    return (
        <Masonry>
            {places.map((place, index) => (
                <PlaceCard key={place.id} place={place} />
            ))}
        </Masonry>
    );
}
```

## 🌐 API Endpoints

### GET /places/feed
**Cursor-based infinite scroll для главной ленты**

**Параметры:**
- `limit` (number, optional): количество элементов (default: 20, max: 100)
- `cursor` (string, optional): курсор для пагинации

**Ответ:**
```json
{
  "success": true,
  "data": [...], // массив PlaceFeedResource
  "meta": {
    "next_cursor": "2026-01-13T16:44:32.000000Z|123",
    "has_more": true
  }
}
```

### GET /places/{id}/similar
**Похожие плейсы (по хэштегам)**

**Параметры:**
- `limit` (number, optional): количество элементов (default: 12, max: 50)

**Ответ:**
```json
{
  "success": true,
  "data": [...] // массив PlaceFeedResource
}
```

## 🪝 Data Hooks

### useInfinitePlacesFeed
```typescript
const {
    data,           // InfiniteData с pages
    error,
    isLoading,      // Первая загрузка
    isFetchingNextPage, // Подгрузка следующей страницы
    hasNextPage,    // Есть ли еще данные
    fetchNextPage,  // Функция загрузки следующей страницы
    isFetching      // Любая загрузка
} = useInfinitePlacesFeed({ limit: 20 });
```

### useSimilarPlaces
```typescript
const {
    data: similarPlaces,
    isLoading,
    error
} = useSimilarPlaces(placeId, { limit: 12 });
```

## 🚀 Как добавить новый фид плейсов

### 1. Создать новый API endpoint
```php
// В PlaceController
public function userPlaces(Request $request, $userId) {
    $query = PlaceFeedQuery::query([
        'limit' => $request->get('limit', 20),
        'cursor' => $request->get('cursor'),
        'user_id' => $userId // Дополнительный фильтр
    ]);

    // ... логика как в feed()
}
```

### 2. Добавить route
```php
// routes.php
Route::get('places/user/{userId}', [PlaceController::class, 'userPlaces']);
```

### 3. Создать новый hook
```typescript
// data/place.ts
export const useUserPlacesFeed = (userId, options) => {
    return useInfiniteQuery({
        queryKey: [API_ENDPOINTS.PLACES_USER, userId, options.limit],
        queryFn: ({ pageParam }) =>
            client.places.user(userId, { limit: options.limit, cursor: pageParam }),
        getNextPageParam: (lastPage) =>
            lastPage.meta?.has_more ? lastPage.meta?.next_cursor : undefined,
    });
};
```

### 4. Использовать существующий UI
```typescript
// В любом компоненте
const UserPlacesFeed = ({ userId }) => {
    const { data, hasNextPage, fetchNextPage } = useUserPlacesFeed(userId, { limit: 20 });
    const places = data?.pages.flatMap(page => page.data || []) || [];

    return <PlaceMasonryGrid places={places} />;
};
```

## 📏 Лучшие практики

### Backend
- **Всегда использовать PlaceFeedQuery** для фидов (не Place::all())
- **PlaceFeedResource** для всех лент, **PlaceResource** только для детальной страницы
- **Cursor pagination** для бесконечной прокрутки
- **Ограничение limit** (max 100 для feed, max 50 для similar)

### Frontend
- **useInfiniteQuery** для cursor-based загрузки
- **Intersection Observer** для автоматической подгрузки
- **Flat map** для объединения страниц: `data?.pages.flatMap(page => page.data || [])`
- **PlaceMasonryGrid** для всех гридов (переиспользование)

### Performance
- **Легковесные запросы** - только необходимые поля
- **Предзагрузка изображений** - thumbnails для быстрого отображения
- **Memoization** - для тяжелых вычислений
- **Lazy loading** - для изображений и видео

## 🔧 Troubleshooting

### Ошибка "Unknown column 'user_id'"
```sql
-- В таблице user_profiles поле называется customer_id
-- Исправить в PlaceFeedQuery:
'profile:id,customer_id,avatar' -- правильно
'profile:id,user_id,avatar'     -- ошибка
```

### Infinite scroll не работает
```
✅ Проверить: hasNextPage === true
✅ Проверить: Intersection Observer подключен
✅ Проверить: fetchNextPage вызывается
✅ Проверить: API возвращает meta.has_more
```

### Masonry layout зависает
```
✅ Проверить: PlaceMasonryGrid получает корректный places array
✅ Проверить: Каждый place имеет уникальный key={place.id}
✅ Проверить: Изображения имеют правильные размеры
✅ Проверить: Нет дубликатов в places array
```

### Данные не обновляются
```
✅ Проверить: queryKey уникален для разных фидов
✅ Проверить: staleTime установлен (30 сек для фидов)
✅ Проверить: refetchOnWindowFocus отключен
```

## 🎯 Итог

Система Places теперь имеет:
- **Масштабируемую архитектуру** с четким разделением ответственности
- **Высокую производительность** благодаря легковесным данным и cursor pagination
- **Переиспользуемые компоненты** для быстрой разработки новых фидов
- **Стабильную работу** без зависаний и ошибок пагинации

Для добавления нового фида достаточно создать API endpoint → Data hook → использовать существующий UI! 🚀
