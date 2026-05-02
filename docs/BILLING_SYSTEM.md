# Система биллинга для платного размещения товаров

## Общее описание

Система автоматического биллинга для маркетплейса, которая взимает плату с продавцов за размещение товаров на платформе. Платежи производятся помесячно на основе количества активных товаров и плейсов у продавца согласно выбранному тарифному плану.

## Архитектура системы

### Компоненты системы

1. **База данных:**
   - `invoices` - таблица счетов
   - `billing_settings` - таблица настроек биллинга
   - `plans` - таблица тарифных планов
   - `users.plan_id` - связь пользователя с тарифным планом

2. **Модели:**
   - `App\Models\Invoice` - модель счета
   - `App\Models\BillingSettings` - модель настроек
   - `App\Models\Plan` - модель тарифного плана

3. **Контроллеры:**
   - `App\Http\Controllers\InvoiceController` - API для продавцов (счета)
   - `App\Http\Controllers\BillingInfoController` - API для продавцов (информация о тарифе)
   - `App\Http\Controllers\BillingPlanController` - API для продавцов (тарифные планы через BillingPlan)
   - `App\Http\Controllers\Admin\AdminInvoiceController` - админ-панель (счета)
   - `App\Http\Controllers\Admin\AdminBillingPlanController` - админ-панель (тарифные планы через BillingPlan)
   - `App\Http\Controllers\Admin\AdminBillingShopController` - админ-панель (магазины с биллингом)
   - `App\Http\Controllers\Admin\AdminBillingSettingsController` - настройки биллинга
   - `App\Http\Controllers\Admin\AdminBillingReportsController` - отчеты

4. **Artisan команды:**
   - `billing:generate-monthly` - генерация месячных счетов
   - `billing:check-overdue` - проверка просроченных счетов

5. **Frontend:**
   - Страница биллинга для продавцов: `/admin/src/pages/[shop]/billing/index.tsx`
   - Страница биллинга для супер-админа: `/admin/src/pages/billing/index.tsx`

## Алгоритм работы системы

### 1. Генерация счетов (ежемесячно)

**Команда:** `php artisan billing:generate-monthly`

**Расписание:** 1-го числа каждого месяца в 03:00 (настраивается в `app/Console/Kernel.php`)

**Алгоритм:**

1. Определяется период:
   - `period_start` = начало предыдущего месяца
   - `period_end` = конец предыдущего месяца

2. Для каждого продавца:
   - Находятся все его магазины
   - Подсчитывается количество активных товаров (статус = `publish`) во всех магазинах
   - Подсчитывается количество плейсов продавца
   - Если товаров > 0 или плейсов > 0:
     - Проверяется, нет ли уже счета за этот период
     - Если счета нет - создается новый счет:
       - `seller_id` = ID продавца
       - `plan_id` = ID тарифного плана (если выбран)
       - `period_start` = начало периода
       - `period_end` = конец периода
       - `total_products` = количество активных товаров
       - `total_places` = количество плейсов
       - `total_amount` = рассчитывается по тарифному плану (см. ниже)
       - `price_per_product` = средняя цена за товар (для совместимости)
       - `status` = `pending`

**Тарифные планы:**

Система использует модель `Plan` (таблица `plans`) для тарифных планов. Каждый план содержит следующие поля:

- `name` - название плана (например, "Free", "Standard", "Pro")
- `price` - базовая стоимость тарифа (месячная подписка)
- `limit_products` - лимит товаров (0 = безлимит)
- `limit_playlists` - лимит плейсов (0 = безлимит)
- `extra_product_price` - цена за товар сверх лимита
- `extra_playlist_price` - цена за плейс сверх лимита
- `link_ozon_wb` - доступна ли ссылка на Ozon/WB
- `utm_tracking` - доступны ли UTM-метки
- `chat_enabled` - включен ли чат
- `featured_collections` - попадание в подборки

**Расчет стоимости:**
Расчет выполняется методом `calculateMonthlyAmount()` модели `Plan`:
- Базовая стоимость тарифа (`price`)
- + Дополнительная стоимость за товары сверх лимита: `(totalProducts - limit_products) × extra_product_price`
- + Дополнительная стоимость за плейсы сверх лимита: `(totalPlaylists - limit_playlists) × extra_playlist_price`

**Примеры расчета:**
- План со стоимостью 200 ₽, лимитом 200 товаров, лимитом 20 плейсов, доп. товар 0.5 ₽, доп. плейс 2 ₽:
  - 50 товаров, 5 плейсов → 200.00 RUB (в пределах лимитов)
  - 250 товаров, 20 плейсов → 200.00 + (250 - 200) × 0.5 = 225.00 RUB
  - 200 товаров, 25 плейсов → 200.00 + (25 - 20) × 2.0 = 210.00 RUB
  - 500 товаров, 30 плейсов → 200.00 + (500 - 200) × 0.5 + (30 - 20) × 2.0 = 370.00 RUB

**Обратная совместимость:**
Если у продавца не выбран тарифный план, используется старый расчет:
- 200 руб за первые 200 товаров
- 0.5 руб за каждый последующий товар

### 2. Оплата счетов

**Процесс оплаты:**

1. Продавец заходит на страницу "Баланс и платежи" в админ-панели
2. Видит список всех своих счетов со статусами:
   - `pending` - ожидает оплаты
   - `paid` - оплачен
   - `overdue` - просрочен

3. Нажимает кнопку "Оплатить" для счета со статусом `pending`

4. Система:
   - Создает платеж в YooKassa через API
   - Сохраняет `payment_id` в счете
   - Возвращает `payment_url` для редиректа

5. Продавец перенаправляется на страницу оплаты YooKassa

6. После оплаты:
   - YooKassa отправляет webhook на `/api/invoices/webhook`
   - Система обновляет счет:
     - `status` = `paid`
     - `paid_at` = текущая дата/время
     - `payment_id` = ID платежа из YooKassa

### 3. Проверка просроченных счетов (ежедневно)

**Команда:** `php artisan billing:check-overdue`

**Расписание:** Ежедневно в 04:00 (настраивается в `app/Console/Kernel.php`)

**Алгоритм:**

1. Получаются настройки:
   - `days_before_overdue` - количество дней до просрочки (по умолчанию 30 дней - постоплата)
   - `overdue_action` - действие при просрочке (по умолчанию `hide_products`)

2. Находятся все счета со статусом `pending`, которые:
   - Созданы более `days_before_overdue` дней назад

3. Для каждого просроченного счета:
   - Статус меняется на `overdue`

4. Если `overdue_action` = `hide_products`:
   - Все товары продавца переводятся в статус `unpublish`
   - Товары скрываются из каталога до оплаты

**Пример:**
- Счет создан 1 января
- `days_before_overdue` = 30 (постоплата через 30 дней)
- 31 января счет автоматически помечается как `overdue`
- Все товары продавца скрываются

### 4. Восстановление товаров после оплаты

После успешной оплаты просроченного счета:
- 

- Товары автоматически не восстанавливаются (требуется ручное действие или дополнительная логика)

## Настройки системы

Все настройки хранятся в таблице `billing_settings`:

| Ключ | Описание | Значение по умолчанию |
|------|----------|----------------------|
| `price_per_product` | Средняя цена за товар (RUB, для совместимости) | Рассчитывается автоматически |
| `currency` | Валюта | RUB |
| `auto_generation` | Автоматическая генерация счетов (1/0) | 1 |
| `generation_day` | День месяца для генерации счетов | 1 |
| `days_before_overdue` | Количество дней до просрочки (постоплата) | 30 |
| `overdue_action` | Действие при просрочке | `hide_products` |

**Возможные значения `overdue_action`:**
- `hide_products` - скрыть товары продавца
- `block_adding` - заблокировать добавление новых товаров (не реализовано)

## API Endpoints

### Для продавцов

**Получить информацию о текущем тарифе и расчет следующего платежа:**
```
GET /api/billing-info/current
Authorization: Bearer {token}
```

**Ответ:**
```json
{
  "success": true,
  "data": {
    "plan": {
      "id": 1,
      "name": "Free",
      "price": 0.00,
      "limit_products": 20,
      "limit_playlists": 3,
      "extra_product_price": null,
      "extra_playlist_price": null
    },
    "current_usage": {
      "total_products": 15,
      "total_playlists": 5,
      "products_within_limit": 15,
      "products_over_limit": 0,
      "playlists_within_limit": 3,
      "playlists_over_limit": 2
    },
    "next_payment": {
      "date": "2024-03-01",
      "total_amount": 200.00,
      "base_price": 200.00,
      "extra_products_cost": 0.00,
      "extra_playlists_cost": 4.00
    }
  }
}
```

**Получить список доступных тарифных планов (через BillingPlan):**
```
GET /api/billing-plans
```

**Получить текущий тарифный план (через BillingPlan):**
```
GET /api/billing-plans/current
Authorization: Bearer {token}
```

**Выбрать тарифный план (через BillingPlan):**
```
POST /api/billing-plans/select
Authorization: Bearer {token}
Content-Type: application/json

{
  "plan_id": 2
}
```

**Получить список счетов:**
```
GET /api/invoices
Authorization: Bearer {token}
```

**Ответ:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "seller_id": 5,
      "plan_id": 2,
      "period_start": "2024-01-01",
      "period_end": "2024-01-31",
      "total_products": 15,
      "total_places": 5,
      "price_per_product": "13.33",
      "total_amount": "200.00",
      "status": "pending",
      "paid_at": null,
      "payment_id": null,
      "created_at": "2024-02-01T03:00:00.000000Z",
      "updated_at": "2024-02-01T03:00:00.000000Z"
    }
  ]
}
```

**Оплатить счет:**
```
POST /api/invoices/{invoice_id}/pay
Authorization: Bearer {token}
```

**Ответ:**
```json
{
  "success": true,
  "payment_url": "https://yookassa.ru/checkout/payments/...",
  "payment_id": "2c5c8e3e-0001-5000-8000-1d5c8e3e0000",
  "invoice_id": 1
}
```

### Для супер-админа

**Управление тарифными планами:**

**Получить список тарифных планов:**
```
GET /api/admin/billing/plans
Authorization: Bearer {token}
Permission: SUPER_ADMIN
```

**Получить активные тарифные планы:**
```
GET /api/admin/billing/plans/active
Authorization: Bearer {token}
Permission: SUPER_ADMIN
```

**Получить тарифный план по ID:**
```
GET /api/admin/billing/plans/{id}
Authorization: Bearer {token}
Permission: SUPER_ADMIN
```

**Создать тарифный план (через BillingPlan):**
```
POST /api/admin/billing/plans
Authorization: Bearer {token}
Permission: SUPER_ADMIN
Content-Type: application/json

{
  "name": "PREMIUM",
  "display_name": "PREMIUM",
  "description": "Премиум тариф",
  "monthly_price": 499.00,
  "product_limit": 500,
  "place_limit": 100,
  "extra_product_price": 0.3,
  "extra_place_price": 1.0,
  "photos_per_product": 10,
  "has_shop": true,
  "has_extended_shop": true,
  "has_ozon_wb_link": true,
  "has_utm_tags": true,
  "analytics_level": "advanced",
  "search_priority": "high",
  "featured_in_collections": true,
  "support_level": "24/7",
  "is_active": true,
  "sort_order": 1
}
```

**Примечание:** В системе используются две модели планов:
- `Plan` (таблица `plans`) - используется для генерации счетов и основной логики биллинга
- `BillingPlan` (таблица `billing_plans`) - используется в новых контроллерах для управления планами через админ-панель

Для генерации счетов используется модель `Plan` с полями:
- `price` (базовая стоимость)
- `limit_products` (лимит товаров)
- `limit_playlists` (лимит плейсов)
- `extra_product_price` (цена за доп. товар)
- `extra_playlist_price` (цена за доп. плейс)

**Обновить тарифный план:**
```
PUT /api/admin/billing/plans/{id}
Authorization: Bearer {token}
Permission: SUPER_ADMIN
```

**Удалить тарифный план:**
```
DELETE /api/admin/billing/plans/{id}
Authorization: Bearer {token}
Permission: SUPER_ADMIN
```

**Получить список магазинов с биллингом:**
```
GET /api/admin/billing/shops
Authorization: Bearer {token}
Permission: SUPER_ADMIN
```

**Получить список всех счетов:**
```
GET /api/admin/billing/invoices
Authorization: Bearer {token}
Permission: SUPER_ADMIN
```

**Получить настройки биллинга:**
```
GET /api/admin/billing/settings
Authorization: Bearer {token}
Permission: SUPER_ADMIN
```

**Обновить настройки биллинга:**
```
POST /api/admin/billing/settings
Authorization: Bearer {token}
Permission: SUPER_ADMIN
Content-Type: application/json

{
  "price_per_product": 10.00,
  "days_before_overdue": 14,
  "overdue_action": "hide_products"
}
```

## Artisan команды

### Генерация месячных счетов

```bash
php artisan billing:generate-monthly
```

**Что делает:**
- Создает счета за предыдущий месяц для всех продавцов с активными товарами
- Учитывает все магазины продавца
- Подсчитывает только товары со статусом `publish`

**Пример вывода:**
```
Generating monthly invoices...
Created invoice for seller 5 (Иван Иванов): 15 products, 200.00 RUB
Created invoice for seller 8 (Петр Петров): 250 products, 225.00 RUB
Monthly invoices generated successfully. Created: 2 invoices.
```

### Проверка просроченных счетов

```bash
php artisan billing:check-overdue
```

**Что делает:**
- Находит все просроченные счета (pending, старше N дней)
- Меняет статус на `overdue`
- Скрывает товары продавца, если настроено

**Пример вывода:**
```
Checking overdue invoices...
Marked invoice 1 as overdue for seller 5
Hidden 15 products for seller 5
Overdue check completed. Marked 1 invoices as overdue, hidden 15 products.
```

## Интеграция с YooKassa

### Настройка

В файле `.env` должны быть указаны:
```env
YOOKASSA_SHOP_ID=your_shop_id
YOOKASSA_SECRET_KEY=your_secret_key
YOOKASSA_IS_TEST=true  # или false для продакшена
```

### Webhook

YooKassa отправляет уведомления на:
```
POST /api/invoices/webhook
```

**Обрабатываемые события:**
- `payment.succeeded` - платеж успешен, счет помечается как оплаченный
- `payment.canceled` - платеж отменен, статус остается `pending`

## Статусы счетов

| Статус | Описание | Действия |
|--------|----------|----------|
| `pending` | Ожидает оплаты | Можно оплатить |
| `paid` | Оплачен | - |
| `overdue` | Просрочен | Товары скрыты, требуется оплата |

## Логика подсчета товаров

При генерации счета учитываются:
- ✅ Товары со статусом `publish` (опубликованные)
- ❌ Товары со статусом `unpublish`, `draft`, `deleted` - не учитываются
- ✅ Все магазины продавца суммируются

**Важно:** Если у продавца несколько магазинов, количество товаров суммируется из всех магазинов.

## Примеры сценариев

### Сценарий 1: Успешная оплата

1. 1 февраля система создает счет за январь (15 товаров → 200.00 RUB по тарифу)
2. Продавец видит счет со статусом `pending`
3. 5 февраля продавец нажимает "Оплатить"
4. Перенаправляется на YooKassa, оплачивает
5. YooKassa отправляет webhook
6. Счет обновляется: `status = paid`, `paid_at = 2024-02-05 14:30:00`

### Сценарий 2: Просрочка платежа (постоплата через 30 дней)

1. 1 февраля создается счет за январь
2. 3 марта (через 30 дней) система автоматически:
   - Меняет статус на `overdue`
   - Скрывает все товары продавца
3. Продавец видит просроченный счет и скрытые товары
4. Продавец оплачивает счет
5. Товары остаются скрытыми (требуется ручное восстановление или дополнительная логика)

### Сценарий 3: Несколько магазинов

1. У продавца 3 магазина:
   - Магазин 1: 10 товаров
   - Магазин 2: 5 товаров
   - Магазин 3: 8 товаров
2. При генерации счета: `total_products = 23`
3. Сумма счета: `200.00 RUB` (минимум за первые 200 товаров)

### Сценарий 4: Большое количество товаров

1. У продавца 500 активных товаров
2. При генерации счета: `total_products = 500`
3. Сумма счета: `200.00 + (500 - 200) × 0.5 = 350.00 RUB`

## Настройка расписания

Расписание команд настраивается в `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Генерация счетов: 1-го числа каждого месяца в 03:00
    $schedule->command('billing:generate-monthly')->monthlyOn(1, '03:00');
    
    // Проверка просроченных: ежедневно в 04:00
    $schedule->command('billing:check-overdue')->dailyAt('04:00');
}
```

**Важно:** Для работы расписания должен быть настроен cron:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Устранение неполадок

### Счета не генерируются

1. Проверьте, что команда зарегистрирована в `Kernel.php`
2. Проверьте настройку `auto_generation` в `billing_settings`
3. Убедитесь, что у продавцов есть активные товары (`status = publish`)
4. Запустите команду вручную: `php artisan billing:generate-monthly`

### Платежи не обрабатываются

1. Проверьте настройки YooKassa в `.env`
2. Проверьте логи: `storage/logs/laravel.log`
3. Убедитесь, что webhook URL доступен извне
4. Проверьте, что webhook не требует CSRF токена

### Товары не скрываются при просрочке

1. Проверьте настройку `overdue_action` в `billing_settings`
2. Убедитесь, что команда `billing:check-overdue` выполняется
3. Проверьте логи выполнения команды

## Тарифные планы

### Структура тарифного плана (модель Plan)

Модель `Plan` (таблица `plans`) используется для генерации счетов и основной логики биллинга. Каждый план содержит следующие параметры:

- **Базовая стоимость** (`price`) - месячная подписка
- **Лимиты:**
  - `limit_products` - максимальное количество товаров (0 = безлимит)
  - `limit_playlists` - максимальное количество плейсов (0 = безлимит)
- **Дополнительные цены:**
  - `extra_product_price` - цена за товар сверх лимита
  - `extra_playlist_price` - цена за плейс сверх лимита
- **Функции:**
  - `link_ozon_wb` - доступна ли ссылка на Ozon/WB
  - `utm_tracking` - доступны ли UTM-метки
  - `chat_enabled` - включен ли чат
  - `featured_collections` - попадание в подборки

### Структура тарифного плана (модель BillingPlan)

Модель `BillingPlan` (таблица `billing_plans`) используется в новых контроллерах для управления планами через админ-панель. Содержит расширенные параметры:

- **Базовая стоимость** (`monthly_price`) - месячная подписка
- **Лимиты:**
  - `product_limit` - максимальное количество товаров (0 = безлимит)
  - `place_limit` - максимальное количество плейсов (0 = безлимит)
- **Дополнительные цены:**
  - `extra_product_price` - цена за товар сверх лимита
  - `extra_place_price` - цена за плейс сверх лимита
- **Функции:**
  - `photos_per_product` - количество фото на товар
  - `has_shop` - доступен ли магазин
  - `has_extended_shop` - расширенный магазин
  - `has_ozon_wb_link` - ссылка на Ozon/WB
  - `has_utm_tags` - UTM-метки
  - `analytics_level` - уровень аналитики (none, basic, advanced)
  - `search_priority` - приоритет в поиске (none, low, high)
  - `featured_in_collections` - попадание в подборки
  - `support_level` - уровень поддержки (basic, standard, 24/7)
  - `is_active` - активен ли план
  - `sort_order` - порядок сортировки

### Выбор тарифного плана

1. По умолчанию новые продавцы получают тариф по умолчанию (через `Plan::getDefault()`)
2. Продавец может выбрать тариф через API: `POST /api/billing-plans/select` (работает с BillingPlan)
3. При генерации счетов используется текущий тариф продавца из модели `Plan` (связь через `users.plan_id`)
4. Если тариф не выбран, используется старый расчет для обратной совместимости через `Invoice::calculateLegacyTariffAmount()`

### Миграция существующих продавцов

При первом запуске миграций:
- Все существующие продавцы могут получить тариф по умолчанию
- Супер-админ может назначить тарифы продавцам через админ-панель
- Старые счета сохранят старую логику расчета

## Дальнейшее развитие

Возможные улучшения:
1. Автоматическое восстановление товаров после оплаты просроченного счета
2. Уведомления продавцам о предстоящих платежах
3. Статистика и аналитика по платежам
4. Экспорт счетов в PDF
5. Интеграция с другими платежными системами
6. Гибкие тарифы (разные цены для разных категорий товаров)
7. Автоматическое переключение тарифов при превышении лимитов
8. Промокоды и скидки на тарифные планы



