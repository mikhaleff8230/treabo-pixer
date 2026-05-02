# Схема взаимодействия ProductEditor с контроллерами

## 📊 Общая архитектура

```
┌─────────────────────────────────────────────────────────────────┐
│                    FRONTEND (React/Next.js)                      │
│                                                                   │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │         ProductEditor.tsx (Визард-едит)                   │  │
│  │                                                            │  │
│  │  • handleSave() - сохранение товара                       │  │
│  │  • handleGroupVariants() - сохранение вариантов           │  │
│  │  • useProductQuery() - загрузка товара                    │  │
│  │  • useUpdateProductMutation() - обновление                │  │
│  │  • useCreateProductMutation() - создание                  │  │
│  └──────────────────────────────────────────────────────────┘  │
│                            │                                     │
│                            ▼                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │         productClient (admin/src/data/client/product.ts)  │  │
│  │                                                            │  │
│  │  • create() → POST /api/products                          │  │
│  │  • update() → PUT /api/products/{id}                      │  │
│  │  • get() → GET /api/products/{slug}                      │  │
│  │  • saveVariants() → POST /api/products/wizard/variants   │  │
│  │  • getVariants() → GET /api/products/wizard/variants    │  │
│  └──────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                            │
                            │ HTTP Requests
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    BACKEND (Laravel/PHP)                         │
│                                                                   │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │              Routes.php (Маршрутизация)                   │  │
│  │                                                            │  │
│  │  POST   /api/products              → ProductController     │  │
│  │  PUT    /api/products/{id}         → ProductController     │  │
│  │  GET    /api/products/{slug}       → ProductController     │  │
│  │  POST   /api/products/wizard/variants → ProductWizard     │  │
│  │  GET    /api/products/wizard/variants → ProductWizard     │  │
│  └──────────────────────────────────────────────────────────┘  │
│                            │                                     │
│        ┌───────────────────┴───────────────────┐                │
│        ▼                                       ▼                │
│  ┌─────────────────────┐          ┌──────────────────────┐    │
│  │ ProductController   │          │ ProductWizardController│    │
│  │                     │          │                       │    │
│  │ • store()           │          │ • saveVariants()      │    │
│  │ • update()          │          │ • getVariants()       │    │
│  │ • show()            │          │ • deleteVariant()     │    │
│  │ • destroy()         │          │ • createGroup()       │    │
│  └─────────────────────┘          └──────────────────────┘    │
│        │                                       │                │
│        └───────────────┬───────────────────────┘                │
│                        ▼                                        │
│              ┌─────────────────────┐                            │
│              │ ProductRepository   │                            │
│              │                     │                            │
│              │ • storeProduct()    │                            │
│              │ • updateProduct()   │                            │
│              │ • findProduct()     │                            │
│              └─────────────────────┘                            │
│                        │                                        │
│                        ▼                                        │
│              ┌─────────────────────┐                            │
│              │ Product Model       │                            │
│              │ (Database)          │                            │
│              └─────────────────────┘                            │
└─────────────────────────────────────────────────────────────────┘
```

## 🔄 Поток данных: Создание/Обновление обычного товара

```
[ProductEditor.tsx]
    │
    │ handleSave(data)
    │
    ▼
[productClient.create() или productClient.update()]
    │
    │ HTTP POST /api/products
    │ или
    │ HTTP PUT /api/products/{id}
    │
    │ Данные: {
    │   name, price, description,
    │   gallery: [{id, thumbnail, original}],
    │   image, categories, tags, ...
    │ }
    │
    ▼
[Routes.php]
    │
    │ Route::apiResource('products', ProductController::class)
    │
    ▼
[ProductController]
    │
    │ • store() - создание
    │ • update() - обновление
    │
    │ Обработка FormData:
    │   - Декодирование JSON строк (gallery, tags, ...)
    │   - Валидация через ProductCreateRequest/ProductUpdateRequest
    │
    ▼
[ProductRepository]
    │
    │ • storeProduct() - создание
    │ • updateProduct() - обновление
    │
    │ Обработка gallery:
    │   - Нормализация массива
    │   - Сохранение в БД (JSON)
    │
    ▼
[Product Model]
    │
    │ • Laravel casts: 'gallery' => 'array'
    │ • Сохранение в БД
    │
    ▼
[Ответ сервера]
    │
    │ { id, slug, name, gallery: [...], ... }
    │
    ▼
[useCreateProductMutation / useUpdateProductMutation]
    │
    │ • Обновление кеша React Query
    │ • Редирект на /edit-wizard
    │
    ▼
[ProductEditor.tsx]
    │
    │ • Обновление формы из ответа
    │ • Отображение сохраненного товара
```

## 🔄 Поток данных: Сохранение вариантов (групповой товар)

```
[ProductEditor.tsx]
    │
    │ handleGroupVariants(variants, groupKey)
    │
    │ Подготовка данных:
    │   variants.map(v => ({
    │     id, name, slug, price,
    │     gallery: [{id, thumbnail, original}],
    │     attribute_values: {...},
    │     ...
    │   }))
    │
    ▼
[productClient.saveVariants()]
    │
    │ HTTP POST /api/products/wizard/variants
    │
    │ Данные: {
    │   group_key: "123",
    │   variants: [
    │     { id: 1, name: "...", gallery: [...] },
    │     { id: 2, name: "...", gallery: [...] },
    │     ...
    │   ]
    │ }
    │
    ▼
[Routes.php]
    │
    │ Route::post('/products/wizard/variants', 
    │             [ProductWizardController::class, 'saveVariants'])
    │
    ▼
[ProductWizardController]
    │
    │ saveVariants(Request $request)
    │
    │ 1. Обработка FormData:
    │    - Декодирование gallery из JSON для каждого варианта
    │
    │ 2. Валидация:
    │    - group_key, variants, variants.*.name, ...
    │
    │ 3. Для каждого варианта:
    │    ├─ Генерация slug (если не передан)
    │    ├─ Проверка уникальности slug
    │    ├─ Создание/обновление Product
    │    ├─ Сохранение gallery:
    │    │   - Нормализация массива
    │    │   - $product->gallery = normalizedGallery
    │    │   - $product->save()
    │    ├─ Синхронизация категорий
    │    └─ Обновление атрибутов
    │
    ▼
[Product Model]
    │
    │ • Product::create() или Product::find()->update()
    │ • Laravel casts: 'gallery' => 'array'
    │ • Сохранение в БД
    │
    ▼
[Ответ сервера]
    │
    │ {
    │   success: true,
    │   data: [
    │     { id: 1, slug: "...", gallery: [...] },
    │     { id: 2, slug: "...", gallery: [...] },
    │     ...
    │   ]
    │ }
    │
    ▼
[ProductEditor.tsx]
    │
    │ • Обновление вариантов в форме
    │ • Отображение сохраненных вариантов
```

## 📋 Детальное описание компонентов

### 1. ProductEditor.tsx (Фронтенд)

**Основные методы:**

- **`handleSave(data, publish)`**
  - Нормализует данные формы
  - Подготавливает `submitData` с gallery
  - Вызывает `productClient.create()` или `productClient.update()`
  - Обрабатывает ответ и обновляет форму

- **`handleGroupVariants(variants, groupKey)`**
  - Подготавливает данные вариантов
  - Вызывает `productClient.saveVariants()`
  - Обрабатывает ответ и обновляет варианты

**React Query хуки:**
- `useProductQuery()` - загрузка товара
- `useCreateProductMutation()` - создание товара
- `useUpdateProductMutation()` - обновление товара

### 2. productClient (Клиент API)

**Методы:**

```typescript
// Обычные операции
productClient.create(data)     → POST /api/products
productClient.update(data)      → PUT /api/products/{id}
productClient.get({slug})       → GET /api/products/{slug}

// Операции с вариантами (визард)
productClient.saveVariants({    → POST /api/products/wizard/variants
  group_key, variants
})
productClient.getVariants({     → GET /api/products/wizard/variants
  group_key
})
```

**Особенности:**
- Поддержка FormData для `update()` (видео, файлы)
- Автоматическое логирование gallery
- Обработка ответов с gallery

### 3. Routes.php (Маршрутизация)

```php
// Обычные операции с товарами
Route::apiResource('products', ProductController::class, [
    'only' => ['store', 'update', 'destroy'],
]);

// Операции визарда (ДО apiResource!)
Route::post('/products/wizard/variants', 
            [ProductWizardController::class, 'saveVariants']);
Route::get('/products/wizard/variants', 
           [ProductWizardController::class, 'getVariants']);
Route::delete('/products/wizard/variants/{id}', 
              [ProductWizardController::class, 'deleteVariant']);
Route::post('/products/wizard/ungroup', 
            [ProductWizardController::class, 'ungroupProducts']);
Route::post('/products/wizard/create-group', 
            [ProductWizardController::class, 'createGroup']);
```

**Важно:** Роуты визарда должны быть ДО `apiResource`, иначе они перехватываются.

### 4. ProductController (Основной контроллер)

**Методы:**

- **`store(ProductCreateRequest $request)`**
  - Создание нового товара
  - Обработка FormData (декодирование JSON)
  - Вызов `ProductRepository::storeProduct()`

- **`update(ProductUpdateRequest $request, $id)`**
  - Обновление существующего товара
  - Обработка FormData (декодирование gallery из JSON)
  - Вызов `ProductRepository::updateProduct()`

- **`show(Request $request, $slug)`**
  - Получение товара по slug
  - Возврат с gallery

**Обработка FormData:**
```php
if ($request->isMethod('post') || multipart/form-data) {
    foreach ($requestData as $key => $value) {
        if (is_string($value) && starts_with('{', '[')) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request->merge([$key => $decoded]);
            }
        }
    }
}
```

### 5. ProductWizardController (Контроллер визарда)

**Методы:**

- **`saveVariants(Request $request)`**
  - Создание/обновление нескольких вариантов
  - Обработка FormData для gallery каждого варианта
  - Генерация slug для новых вариантов
  - Сохранение gallery для каждого варианта

**Обработка gallery:**
```php
// Для каждого варианта
if (isset($variantData['gallery'])) {
    // Декодирование из JSON (если строка)
    // Нормализация массива
    // Фильтрация валидных элементов
    $product->gallery = $normalizedGallery;
    $product->save();
}
```

**Генерация slug:**
```php
private function generateSlug($name, $productId = null)
{
    $baseSlug = Str::slug($name);
    if ($productId) {
        return $baseSlug . '-' . $productId;
    }
    return $baseSlug; // Уникальность в цикле проверки
}
```

### 6. ProductRepository (Репозиторий)

**Методы:**

- **`storeProduct($request, $setting)`**
  - Создание товара в БД
  - Обработка gallery (нормализация)
  - Генерация internal_article
  - Связи с категориями, тегами, атрибутами

- **`updateProduct($request, $id, $setting)`**
  - Обновление товара в БД
  - Обработка gallery ДО `only()`
  - Принудительное сохранение gallery отдельно
  - Обновление связей

**Обработка gallery:**
```php
// Обработка ДО only()
if ($request->has('gallery')) {
    $normalizedGallery = normalizeGallery($request->input('gallery'));
    $data['gallery'] = $normalizedGallery;
}

// Сохранение отдельно
if (isset($data['gallery'])) {
    $galleryToSave = $data['gallery'];
    unset($data['gallery']);
    $product->update($data);
    $product->gallery = $galleryToSave;
    $product->save();
}
```

## 🔑 Ключевые моменты

### 1. Обработка gallery

**ProductController:**
- Декодирует gallery из JSON строки (FormData)
- Передает в ProductRepository как массив

**ProductWizardController:**
- Декодирует gallery для каждого варианта
- Нормализует и сохраняет напрямую в модель

**ProductRepository:**
- Обрабатывает gallery ДО `only()`
- Сохраняет отдельно после `update()`

### 2. Генерация slug

**ProductController:**
- Использует `ProductRepository::customSlugify()`
- Автоматическая генерация при создании

**ProductWizardController:**
- Использует `Str::slug()` + проверка уникальности
- Для существующих: `slug-id`
- Для новых: базовый slug + счетчик при конфликте

### 3. Формат данных gallery

```typescript
// Фронтенд отправляет:
gallery: [
  {
    id: 1,
    thumbnail: "https://...",
    original: "https://...",
    file_name: "image.jpg"
  },
  ...
]

// Бэкенд сохраняет (JSON в БД):
{
  "gallery": [
    {
      "id": 1,
      "thumbnail": "https://...",
      "original": "https://...",
      "file_name": "image.jpg"
    },
    ...
  ]
}
```

### 4. Различия между контроллерами

| Аспект | ProductController | ProductWizardController |
|--------|-------------------|------------------------|
| Назначение | CRUD операции | Работа с вариантами |
| Обработка gallery | Через ProductRepository | Напрямую в модель |
| Количество товаров | Один за раз | Несколько вариантов |
| Генерация slug | Автоматическая | С проверкой уникальности |
| Формат ответа | Один товар | Массив товаров |

## 🚨 Проблемные места (исправлены)

1. **Gallery терялась в `$request->only()`**
   - ✅ Исправлено: обработка ДО `only()`

2. **Gallery не декодировалась из FormData**
   - ✅ Исправлено: декодирование JSON в контроллерах

3. **Дополнительный `save()` перезаписывал gallery**
   - ✅ Исправлено: сохранение gallery отдельно

4. **Длинные slug с timestamp**
   - ✅ Исправлено: упрощенная генерация slug

5. **React Query затирал gallery**
   - ✅ Исправлено: защита в `onSuccess`

