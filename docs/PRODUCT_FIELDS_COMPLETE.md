# Полный список всех полей товара (Product)

## 📋 Основные поля из базы данных (таблица `products`)

### Идентификаторы и базовая информация
- `id` - ID товара (primary key)
- `name` - Название товара
- `slug` - URL-адрес товара (уникальный)
- `description` - Описание товара
- `language` - Язык товара
- `status` - Статус товара (draft, publish, under_review, approved, rejected, unpublish)

### Типы и категории
- `type_id` - ID типа товара (связь с таблицей `types`)
- `product_type` - Тип товара (simple, variable)
- `shop_id` - ID магазина (связь с таблицей `shops`)

### Цены и стоимость
- `price` - Цена товара (для простых товаров)
- `sale_price` - Цена со скидкой
- `min_price` - Минимальная цена (для вариативных товаров)
- `max_price` - Максимальная цена (для вариативных товаров)

### Количество и единицы измерения
- `quantity` - Количество товара на складе
- `unit` - Единица измерения (шт., кг, м и т.д.)

### Артикул и идентификация
- `sku` - Артикул товара (Stock Keeping Unit)

### Медиа файлы (JSON массивы)
- `image` - Основное изображение товара (JSON: {original, thumbnail})
- `gallery` - Галерея изображений (JSON массив)
- `video` - Видео товара (JSON массив, устаревшее, используется `product_videos`)

### Цифровые товары
- `is_digital` - Флаг цифрового товара (boolean)
- `digital_file` - Цифровой файл (связь через `digital_files`)

### Внешние товары
- `is_external` - Флаг внешнего товара (boolean)
- `external_product_url` - URL внешнего товара
- `external_product_button_text` - Текст кнопки для внешнего товара

### Габариты и вес
- `height` - Высота товара (string)
- `length` - Длина товара (string)
- `width` - Ширина товара (string)
- `weight` - Вес товара (decimal, в граммах)

### Дополнительные флаги
- `in_stock` - Товар в наличии (boolean)
- `is_taxable` - Облагается налогом (boolean)
- `preview_url` - URL превью (устаревшее)

### Авторы и производители
- `author_id` - ID автора (связь с таблицей `authors`)
- `manufacturer_id` - ID производителя (связь с таблицей `manufacturers`)

### Доставка
- `shipping_class_id` - ID класса доставки (связь с таблицей `shipping_classes`)

### Временные метки
- `created_at` - Дата создания
- `updated_at` - Дата обновления
- `deleted_at` - Дата удаления (soft delete)

---

## 🔗 Связи (Relationships)

### Many-to-Many связи
- `categories` - Категории товара (через `category_product`)
- `tags` - Теги товара (через `product_tag`)
- `variations` - Вариации товара (через `attribute_product`, связь с `attribute_values`)
- `attributes` - Атрибуты товара (через `product_attribute_values`, с pivot: `value`, `attribute_value_id`)
- `dropoff_locations` - Места выдачи (через `dropoff_location_product`)
- `pickup_locations` - Места получения (через `pickup_location_product`)
- `deposits` - Залоги (через `deposit_product`)
- `persons` - Персоны (через `person_product`)
- `features` - Особенности (через `feature_product`)
- `places` - Места (через `place_product`)
- `orders` - Заказы (через pivot таблицу)

### One-to-Many связи
- `variation_options` - Варианты товара (таблица `variations`)
- `videos` - Видео товара (таблица `product_videos`)
- `reviews` - Отзывы (таблица `reviews`)
- `questions` - Вопросы (таблица `questions`)
- `wishlists` - Списки желаний (таблица `wishlists`)
- `availabilities` - Доступность (таблица `availabilities`, polymorphic)

### BelongsTo связи
- `type` - Тип товара (таблица `types`)
- `shop` - Магазин (таблица `shops`)
- `author` - Автор (таблица `authors`)
- `manufacturer` - Производитель (таблица `manufacturers`)
- `shipping` - Класс доставки (таблица `shipping_classes`)

### MorphOne связи
- `digital_file` - Цифровой файл (таблица `digital_files`, polymorphic)

---

## 📊 Вычисляемые поля (Appended Attributes)

### Рейтинги и отзывы
- `ratings` - Средний рейтинг товара (вычисляется из `reviews`)
- `total_reviews` - Общее количество отзывов
- `rating_count` - Количество отзывов по рейтингам
- `my_review` - Мой отзыв (для авторизованного пользователя)

### Дополнительные
- `total_downloads` - Общее количество скачиваний (для цифровых товаров)
- `in_wishlist` - В списке желаний (boolean, для авторизованного пользователя)
- `blocked_dates` - Заблокированные даты (для аренды)
- `translated_languages` - Переведенные языки (массив)

---

## 🎯 Поля для вариативных товаров (Variable Products)

### Вариации (Variations)
- `variations` - Массив вариаций (связь с `attribute_values` через `attribute_product`)
  - Структура: `[{attribute: Attribute, value: AttributeValue[]}]`

### Варианты (Variation Options)
- `variation_options` - Массив вариантов товара (таблица `variations`)
  - `id` - ID варианта
  - `product_id` - ID товара
  - `title` - Название варианта
  - `price` - Цена варианта (string)
  - `sale_price` - Цена со скидкой (string, nullable)
  - `quantity` - Количество варианта (integer)
  - `sku` - Артикул варианта
  - `options` - Опции варианта (JSON массив: `[{name: string, value: string}]`)
  - `image` - Изображение варианта (JSON)
  - `is_digital` - Цифровой вариант (boolean)
  - `is_disable` - Вариант отключен (boolean)
  - `digital_file` - Цифровой файл варианта (связь)
  - `created_at` - Дата создания
  - `updated_at` - Дата обновления

---

## 📝 Поля из мета-таблицы (Metable)

Товар использует трейт `Metable`, что позволяет хранить дополнительные поля в таблице `products_meta`:
- `key` - Ключ мета-поля
- `value` - Значение мета-поля

Примеры использования:
- Любые дополнительные настройки товара
- Кастомные поля, специфичные для проекта

---

## 🎬 Поля для видео (Product Videos)

Таблица `product_videos`:
- `id` - ID видео
- `product_id` - ID товара
- `url` - URL видео файла
- `preview_url` - URL превью
- `poster_url` - URL постера
- `thumbnail_url` - URL миниатюры
- `duration` - Длительность видео (decimal)
- `width` - Ширина видео (integer)
- `height` - Высота видео (integer)
- `file_size` - Размер файла (bigInteger)
- `mime_type` - MIME тип файла
- `created_at` - Дата создания
- `updated_at` - Дата обновления

---

## 📦 Поля для цифровых файлов (Digital Files)

Таблица `digital_files` (polymorphic):
- `id` - ID файла
- `fileable_type` - Тип модели (Product)
- `fileable_id` - ID товара
- `attachment_id` - ID вложения
- `url` - URL файла
- `file_name` - Имя файла
- `created_at` - Дата создания
- `updated_at` - Дата обновления

---

## 🔍 Поля из ProductRepository::$dataArray

Список полей, которые обрабатываются в репозитории:
```php
[
    'name',
    'slug',
    'price',
    'sale_price',
    'max_price',
    'min_price',
    'type_id',
    'author_id',
    'language',
    'manufacturer_id',
    'product_type',
    'quantity',
    'unit',
    'is_digital',
    'is_external',
    'external_product_url',
    'external_product_button_text',
    'description',
    'sku',
    'preview_url',
    'image',
    'gallery',
    'video',
    'status',
    'height',
    'length',
    'width',
    'weight',
    'in_stock',
    'is_taxable',
    'shop_id',
]
```

---

## 📋 Поля из TypeScript интерфейса Product

```typescript
interface Product {
  id: string;
  translated_languages: string[];
  shop_id: string;
  name: string;
  slug: string;
  type: Type;
  product_type: ProductType;
  max_price?: number;
  min_price?: number;
  categories: Category[];
  variations?: AttributeValue[];
  variation_options?: Variation[];
  digital_file?: DigitalFile;
  pivot?: OrderProductPivot;
  orders: Order[];
  description?: string;
  in_stock?: boolean;
  is_digital?: boolean;
  is_external?: boolean;
  is_taxable?: boolean;
  sale_price?: number;
  video?: { url: string }[];
  sku?: string;
  gallery?: Attachment[];
  image?: Attachment;
  status?: ProductStatus;
  height?: string;
  length?: string;
  width?: string;
  weight?: number;
  price: number;
  quantity?: number;
  unit?: string;
  external_product_url?: string;
  external_product_button_text?: string;
  created_at: string;
  updated_at: string;
  ratings: number;
  shop?: Shop;
}
```

---

## 🎨 Поля из формы создания/редактирования

### Базовые поля (все товары)
- `name` - Название товара
- `slug` - URL-адрес
- `description` - Описание
- `type` - Тип товара (объект с id, name)
- `product_type` - Тип товара (simple/variable)
- `category` - Категория (одна категория)
- `categories` - Категории (массив, для обратной совместимости)
- `tags` - Теги
- `status` - Статус
- `image` - Основное изображение
- `gallery` - Галерея изображений
- `video` - Видео файл (File)
- `video_as_cover` - Использовать видео как обложку (boolean)
- `is_digital` - Цифровой товар
- `is_external` - Внешний товар
- `external_product_url` - URL внешнего товара
- `external_product_button_text` - Текст кнопки
- `digital_file_input` - Цифровой файл (AttachmentInput)
- `height` - Высота
- `length` - Длина
- `width` - Ширина
- `weight` - Вес
- `in_stock` - В наличии
- `is_taxable` - Облагается налогом
- `author_id` - ID автора
- `manufacturer_id` - ID производителя
- `attribute_values` - Значения атрибутов (Record<string, any>)

### Поля для простых товаров (Simple)
- `price` - Цена
- `sale_price` - Цена со скидкой
- `quantity` - Количество
- `sku` - Артикул
- `unit` - Единица измерения

### Поля для вариативных товаров (Variable)
- `variations` - Вариации (массив: `[{attribute: Attribute, value: AttributeValue[]}]`)
- `variation_options` - Варианты товара (массив объектов Variation)
- `quantity` - Автоматически рассчитывается из суммы вариантов
- `min_price` - Автоматически рассчитывается
- `max_price` - Автоматически рассчитывается

---

## 📊 Статистика полей

### Всего полей в таблице products: ~35
### Связей (relationships): ~15
### Вычисляемых полей (appended): ~7
### Поля для вариативных товаров: ~10
### Поля для видео: ~10
### Поля для цифровых файлов: ~8

**Итого: ~85+ различных полей и связей**

---

## 💡 Идеи для использования

1. **Экспорт/Импорт товаров** - использовать полный список для создания CSV/Excel экспорта
2. **API документация** - полный список всех доступных полей
3. **Валидация** - проверка всех обязательных полей
4. **Миграции** - понимание структуры БД для новых миграций
5. **Формы** - создание динамических форм на основе этого списка
6. **Поиск** - индексация всех полей для полнотекстового поиска
7. **Аналитика** - отслеживание использования полей
8. **Оптимизация** - выявление неиспользуемых полей

