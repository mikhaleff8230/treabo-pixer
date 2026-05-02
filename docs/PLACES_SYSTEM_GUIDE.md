# 🏗️ Places System Architecture Guide

## 📋 Обзор

Система Places построена на **гибридной архитектуре** с постепенным переходом от page-based к cursor-based пагинации. Используется паттерн **"Data Hook → Orchestrator → Pure UI"** для максимальной переиспользуемости компонентов.

## 📂 Структура файлов

### Frontend (Next.js)

#### Страницы
- `shop/src/pages/places/index.tsx` - Главная страница плейсов
- `shop/src/pages/places/element/[hashtagSlug].tsx` - Страницы хэштегов
- `shop/src/pages/feed.tsx` - Страница фида (избранные/все плейсы)

#### Компоненты
- `shop/src/components/places/places-feed.tsx` - **PlacesFeed** (оркестратор)
- `shop/src/components/places/place-masonry-grid.tsx` - **PlaceMasonryGrid** (грид)
- `shop/src/components/place/place-card.tsx` - **PlaceCard** (карточка)

#### Хуки данных
- `shop/src/data/place.ts`
  - `useUnifiedPlacesFeed()` - унифицированный хук данных
  - `usePlaces()` - старый page-based хук
  - `useInfinitePlacesFeed()` - новый cursor-based хук

#### API клиенты
- `shop/src/data/client/index.ts` - Places API методы
- `shop/src/data/client/endpoints.ts` - API endpoints константы

### Backend (Laravel)

#### Контроллеры
- `pixer-api/packages/marvel/src/Http/Controllers/PlaceController.php`
  - `index()` - GET /places (старый API)
  - `feed()` - GET /places/feed (новый API)
  - `similar()` - GET /places/{id}/similar

#### Ресурсы
- `pixer-api/packages/marvel/src/Http/Resources/PlaceResource.php` - Полные данные
- `pixer-api/packages/marvel/src/Http/Resources/PlaceFeedResource.php` - Оптимизированные данные

#### Запросы
- `pixer-api/packages/marvel/src/Http/Queries/PlaceFeedQuery.php` - Cursor-based запросы

## 🗂️ Архитектура страниц

### 1️⃣ /places (Главная страница)

**Файл:** `places/index.tsx`
```typescript
export const getServerSideProps = async () => {
  const response = await client.places.all({ limit: 20 }); // Старый API
  return { initialPlaces: response.data, initialPaginatorInfo: response.meta };
};

export default function PlacesPage({ initialPlaces, initialPaginatorInfo }) {
  return (
    <PlacesFeed
      limit={20}
      initialPlaces={initialPlaces}
      initialPaginatorInfo={initialPaginatorInfo}
    />
  );
}
```

**Хук данных:** `useUnifiedPlacesFeed` → `usePlaces` (page-based)
**Пагинация:** Page-based через `usePlaces`

---

### 2️⃣ /places/element/[hashtagSlug] (Хэштеги)

**Файл:** `places/element/[hashtagSlug].tsx`
```typescript
export const getServerSideProps = async ({ params }) => {
  const response = await client.places.feed({
    hashtag_slug: params.hashtagSlug,
    limit: 20
  }); // Новый API
  return { initialPlaces: response.data, initialPaginatorInfo: response.meta };
};

export default function HashtagPage({ initialPlaces, initialPaginatorInfo }) {
  const filters = { hashtag_slug: hashtagSlug };
  return (
    <PlacesFeed
      limit={20}
      filters={filters}
      initialPlaces={initialPlaces}
      initialPaginatorInfo={initialPaginatorInfo}
    />
  );
}
```

**Хук данных:** `useUnifiedPlacesFeed` → `useInfinitePlacesFeed` (cursor-based)
**Пагинация:** Cursor-based через `useInfinitePlacesFeed`

---

### 3️⃣ /feed (Фид плейсов)

**Файл:** `feed.tsx`
```typescript
export const getServerSideProps = async () => {
  // Для неавторизованных - новый API
  const response = await client.places.feed({ limit: 20 });
  return { initialPlaces: response.data, initialPaginatorInfo: response.meta };
};

export default function FeedPage({ initialPlaces, initialPaginatorInfo }) {
  const filters = isAuthorized ? { favorited_by: me.id } : {};
  return (
    <PlacesFeed
      limit={20}
      filters={filters}
      initialPlaces={initialPlaces}
      initialPaginatorInfo={initialPaginatorInfo}
    />
  );
}
```

**Хук данных:**
- Неавторизованные: `useUnifiedPlacesFeed` → `usePlaces` (page-based)
- Авторизованные: `useUnifiedPlacesFeed` → `usePlaces` + `favorited_by` фильтр

## 🔧 Детали компонентов

### PlacesFeed (Оркестратор)

**Файл:** `places-feed.tsx`
**Роль:** Управляет загрузкой данных и infinite scroll

```typescript
export default function PlacesFeed({
  limit = 20,
  filters = {},
  initialPlaces = [],
  initialPaginatorInfo = null
}) {
  // Выбор источника данных
  const hasFilters = Object.keys(filters).length > 0;

  // Унифицированный хук данных
  const { places, isLoading, hasNextPage, fetchNextPage, error } =
    useUnifiedPlacesFeed({ limit, filters });

  // Преобразование SSR данных
  const transformedInitialPlaces = initialPlaces.map(place => ({
    // Преобразование preview_image → images array
  }));

  // Выбор отображаемых данных
  const displayPlaces = places.length > 0 ? places : transformedInitialPlaces;

  return (
    <>
      <PlaceMasonryGrid places={displayPlaces} onLastItemRef={lastElementRef} />
      {isFetchingNextPage && <Loader />}
      {hasNextPage && (
        <Button onClick={fetchNextPage}>Показать ещё</Button>
      )}
    </>
  );
}
```

### useUnifiedPlacesFeed (Унифицированный хук)

**Файл:** `place.ts`
**Роль:** Абстракция выбора источника данных

```typescript
export const useUnifiedPlacesFeed = ({ limit, filters, initialData }) => {
  const hasFilters = Object.keys(filters).length > 0;

  // Выбор хука на основе фильтров
  const activeHook = hasFilters
    ? usePlaces({ limit, filters })              // Page-based для фильтров
    : useInfinitePlacesFeed({ limit, filters }); // Cursor-based для общего

  // Унифицированный интерфейс
  return {
    places: hasFilters
      ? activeHook.places || []
      : activeHook.data?.pages?.flatMap(page => page?.data || []) || [],
    isLoading: activeHook.isLoading,
    hasNextPage: activeHook.hasNextPage,
    fetchNextPage: hasFilters ? activeHook.loadMore : activeHook.fetchNextPage,
    error: activeHook.error,
  };
};
```

### PlaceMasonryGrid (UI компонент)

**Файл:** `place-masonry-grid.tsx`
**Роль:** Чистое отображение masonry layout

```typescript
export default function PlaceMasonryGrid({ places, onLastItemRef }) {
  const breakpointColumnsObj = {
    default: 6, 1600: 5, 1400: 4, 1100: 3, 500: 2
  };

  return (
    <Masonry
      breakpointCols={breakpointColumnsObj}
      className="flex w-auto -ml-4"
      columnClassName="flex flex-col gap-4 pl-4"
    >
      {places.map((place, index) => {
        const isLastItem = index === places.length - 1;
        return (
          <div
            key={place.id}
            className="break-inside-avoid"
            ref={isLastItem ? onLastItemRef : null}
          >
            <PlaceCard place={place} />
          </div>
        );
      })}
    </Masonry>
  );
}
```

## 🌐 API Endpoints

| Method | Endpoint | Controller | Описание |
|--------|----------|------------|----------|
| GET | `/places` | `PlaceController@index` | Старый API, все данные |
| GET | `/places/feed` | `PlaceController@feed` | Новый API, cursor pagination |
| GET | `/places/{id}/similar` | `PlaceController@similar` | Похожие плейсы |

## 🔄 Поток данных

### SSR (Server-Side Rendering)
```
Страница.getServerSideProps()
  → client.places.all/feed()
  → Laravel API
  → PlaceFeedQuery/PlaceResource
  → JSON response
  → initialPlaces (в props)
```

### Клиентская загрузка
```
PlacesFeed → useUnifiedPlacesFeed
  → usePlaces / useInfinitePlacesFeed
  → client API call
  → Laravel API
  → append to places array
  → re-render PlaceMasonryGrid
```

### Infinite Scroll
```
IntersectionObserver (в PlacesFeed)
  → fetchNextPage()
  → useInfiniteQuery
  → client.places.feed({ cursor: "..." })
  → append новые данные
```

## 📊 Сравнение архитектур

| Аспект | Page-based (/places) | Cursor-based (/places/element/*) |
|--------|---------------------|----------------------------------|
| **API** | `/places` | `/places/feed` |
| **Хук** | `usePlaces` | `useInfinitePlacesFeed` |
| **Пагинация** | `page=1,2,3...` | `cursor="2024-01-13T16:44:32..."` |
| **Данные** | Полные | Оптимизированные |
| **Изображения** | Все | Preview + ограничение |
| **Производительность** | Тяжелее | Легче |

## 🚀 Как добавить новый фид

### 1. Создать страницу
```typescript
// pages/new-feed.tsx
export const getServerSideProps = async () => {
  const response = await client.places.feed({ /* custom filters */ });
  return { initialPlaces: response.data };
};

export default function NewFeedPage() {
  return <PlacesFeed filters={/* custom */} initialPlaces={...} />;
}
```

### 2. Добавить API фильтр
```php
// PlaceController@feed
if (isset($params['custom_filter'])) {
  $query->where(/* custom condition */);
}
```

### 3. Использовать существующие компоненты
```typescript
// Всё работает автоматически через PlacesFeed + PlaceMasonryGrid
<PlacesFeed filters={{ custom_filter: value }} />
```

## 🔧 Troubleshooting

### Фото не показываются
```
✅ Проверить: PlaceCard получает place.images array
✅ Проверить: useUnifiedPlacesFeed преобразует preview_image
✅ Проверить: API возвращает корректные данные
✅ Проверить: PlaceFeed передает displayPlaces в PlaceMasonryGrid
```

### Infinite scroll не работает
```
✅ Проверить: IntersectionObserver подключен
✅ Проверить: hasNextPage = true
✅ Проверить: fetchNextPage вызывается
✅ Проверить: API возвращает meta.has_more
```

### Данные не обновляются
```
✅ Проверить: queryKey уникален
✅ Проверить: staleTime установлен
✅ Проверить: refetchOnWindowFocus отключен
```

## 🎯 Резюме

- **PlacesFeed** - универсальный оркестратор для всех фидов
- **useUnifiedPlacesFeed** - абстракция выбора источника данных
- **PlaceMasonryGrid** - чистый UI компонент
- **Гибридная архитектура** - постепенный переход к cursor-based
- **Переиспользуемые компоненты** - один код для всех страниц

**Система готова к расширению и поддерживает обе архитектуры пагинации!** 🚀


🎯 РЕЗЮМЕ
Хук	Для чего	Где использовать
usePlaces	Page-based пагинация	Фильтры, избранные
useInfinitePlacesFeed	Cursor-based пагинация	Основные фиды, производительность
useUnifiedPlacesFeed	Автоматический выбор	Все страницы (рекомендуется)
useSimilarPlaces	Похожие плейсы	Детальная страница


Страница /places
├── PlacesFeed (без filters)
│   ├── useUnifiedPlacesFeed (hasFilters=false)
│   │   └── useInfinitePlacesFeed (cursor-based, без initialData)
│   └── displayPlaces = allPlaces || transformedInitialPlaces
└── PlaceMasonryGrid
    └── PlaceCard (получает images array)