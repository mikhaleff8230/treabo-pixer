# Цифровые продукты: что сделано и что доделать

Инструкция для продолжения работы над системой цифровых товаров (маркетплейс).

---

## Что уже реализовано

### Архитектура backend

- **`App\Services\DigitalAccessGrantService`** — проверка права на доступ (`grant`): покупка, заказ по `tracking_number`, legacy `ordered_files`.
- **`App\Services\DigitalProductAccessService`** — единая выдача контента: выбор типа → handler → JSON `{ product_id, type, payload }`.
- **Strategy:** `App\Services\DigitalProducts\Contracts\DigitalProductHandlerInterface` + хендлеры в `Handlers/` (`File`, `Prompt`, `Link`, `Account`, `Key`, `Subscription`).
- **`DownloadController`** (пакет marvel) не содержит `switch` по типам; вызывает сервисы приложения.

### Endpoint

- **`GET /products/{id}/access`** — основной способ выдачи цифрового контента после покупки (плюс `?tracking_number=`, опционально `?debug=1`).

### База данных

- Таблица **`products`**: `digital_product_type`, `prompt_text`, `external_url`, `account_data` (JSON), `subscription_days`, и ранее добавленные поля (`file_url`, `subscription_data`, `key_data` и т.д.).
- Таблица **`product_keys`**: пул ключей, поля `used_by`, **`used_at`** (добавлено миграцией).
- Таблица **`subscriptions`**: подписки на цифровой товар (`user_id`, `product_id`, `expires_at`). Новые установки создают её сразу; старые могли иметь `product_subscriptions` — миграция переименовывает в `subscriptions`.
- Миграция **`2026_04_18_120000_digital_products_tz_production`**: `used_at` для ключей, rename таблицы подписок, для **MySQL** — `digital_product_type` как `VARCHAR(50)`.

### Логика по типам

| Тип            | Поведение |
|----------------|-----------|
| `file`         | Одноразовый download token, URL в `payload.download_url`. |
| `prompt`       | `payload.prompt_text`. |
| `link`         | `payload.external_url`. |
| `account`      | `payload.account_data` (JSON). |
| `key`          | Выдача из `product_keys`; повторно тому же пользователю — тот же ключ; при первой выдаче `used_by` + `used_at`. |
| `subscription` | Активная запись в `subscriptions` → `expires_at`; только истёкшие записи → исключение **`SUBSCRIPTION_EXPIRED`**; первая выдача создаёт запись при `subscription_days >= 1`. |

### Админка (seller)

- В визарде товара, шаг **«Цена и наличие»** (`StepPricing`): выбор **Digital Product Type** и условные поля (файл только для типа `file`, промпт, ссылка, JSON аккаунта, многострочные ключи, дни подписки).
- При сохранении товара API принимает **`digital_license_keys`** (текст); репозиторий синхронизирует **только неиспользованные** ключи; при типе не `key` неиспользованные ключи очищаются.
- Для редактирования списка ключей в ответ **`GET /products/{slug}?with=...`** для владельца добавлено поле **`digital_license_keys`** (строка, ключи через перевод строки).

### Витрина (shop)

- Страница покупок: улучшен показ **account** (логин/пароль, копирование) и **subscription** (дата «Доступ активен до …»).

### Прочее

- В **`AppServiceProvider`** зарегистрированы bindings хендлеров (при необходимости можно упростить).
- Константа **`SUBSCRIPTION_EXPIRED`** в `packages/marvel/config/constants.php` — нужен текст в языковых файлах уведомлений (как у других `ERROR.*`).

---

## Что доделать дальше (чеклист)

### Обязательно после pull

1. Выполнить миграции: `php artisan migrate`.
2. Выполнить: `composer dump-autoload`.
3. Проверить перевод/сообщение для кода **`SUBSCRIPTION_EXPIRED`** на фронте и в API-ответах.

### База и совместимость

- [ ] На **PostgreSQL/SQLite** миграция с `ALTER TABLE ... MODIFY digital_product_type` не выполняется (только MySQL). При необходимости добавить отдельные ветки для других СУБД.
- [ ] Если в проде уже есть данные в **`key_data`** / **`subscription_data`** на `products`, решить политику миграции в **`product_keys`** / **`subscriptions`** (разовый скрипт или оставить только новые товары).

### Безопасность и API

- [ ] Убедиться, что **публичный** `GET /products/{slug}` **не** отдаёт `digital_license_keys`, `account_data`, промпты и т.д. без необходимости (сейчас ключи подмешиваются только при `with` + права владельца).
- [ ] При **`MarvelException(SUBSCRIPTION_EXPIRED)`** на REST проверить, что клиент (shop/admin) показывает понятное сообщение (обработчик исключений Laravel).

### Админка

- [ ] При смене типа с `account` на другой при необходимости **очищать** `account_data` на бэкенде (сейчас при типе не `account` объект может не отправляться — старые данные в БД могут остаться).
- [ ] Валидация **JSON** для account на бэкенде (структура `login`/`password`), не только на фронте.
- [ ] Опционально: отдельный UI для **остатка ключей** (сколько свободно / выдано) без открытия сырого текста.

### Витрина

- [ ] Страница **карточки товара** до покупки: показывать тип цифрового продукта или нет — по продуктовому ТЗ.
- [ ] **Заказы (`order-items`)**: единая подпись кнопки «Скачать / Промпт / …» и обработка ошибки истекшей подписки (toast с текстом сервера).

### Тесты

- [ ] Feature-тесты: `grant` → `access` для каждого типа; 403 без покупки; ключ один на пользователя; подписка активна / истекла.
- [ ] Тест синхронизации **`digital_license_keys`** при create/update товара.

### Документация для команды

- [ ] Описать для контент-менеджеров: как заполнять ключи (один на строку), `subscription_days`, разница между **`external_url`** (тип link) и **`external_product_url`** (облако для типа file).

---

## Полезные пути в репозитории

| Назначение | Путь |
|------------|------|
| Grant-сервис | `app/Services/DigitalAccessGrantService.php` |
| Доступ по типам | `app/Services/DigitalProductAccessService.php` |
| Хендлеры | `app/Services/DigitalProducts/Handlers/` |
| Маршрут access | `packages/marvel/src/Rest/Routes.php` → `DownloadController@accessPurchasedProduct` |
| Синхронизация ключей | `packages/marvel/src/Database/Repositories/ProductRepository.php` → `syncDigitalLicenseKeysFromRequest` |
| Админка типов | `admin/src/components/product/editor/steps/StepPricing.tsx` |
| Форма/сабмит | `admin/src/components/product/editor/ProductEditor.tsx` |
| Миграции digital | `packages/marvel/database/migrations/` (`2026_04_16_*`, `2026_04_17_*`, `2026_04_18_*`) |

---

*Документ отражает состояние на момент последних правок по цифровым продуктам; при изменении кода обновляйте этот файл.*
