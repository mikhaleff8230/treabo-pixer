# Система подписки PRO

## Общее описание

Подписка PRO — это отдельная система подписки, которая предоставляет продавцам доступ к расширенным функциям платформы. Подписка работает независимо от тарифных планов (`Plan`) и оплачивается отдельно.

**Основные характеристики:**
- Стоимость: **1990 ₽** за 30 дней
- Способы оплаты: баланс продавца или YooKassa
- Автоматическое истечение через 30 дней
- Предоставляет доступ к расширенным функциям (ссылки на Ozon/WB и др.)

## Архитектура системы

### Компоненты системы

1. **База данных:**
   - `pro_subscriptions` - таблица подписок PRO
   - `invoices` - таблица счетов (связь через `invoice_id`)
   - `seller_balances` - баланс продавца (для оплаты через баланс)

2. **Модели:**
   - `App\Models\ProSubscription` - модель подписки PRO
   - `App\Models\Invoice` - модель счета
   - `App\Models\SellerBalance` - модель баланса продавца

3. **Контроллеры:**
   - `App\Http\Controllers\ProSubscriptionController` - API для управления подпиской
   - `App\Http\Controllers\InvoiceController` - обработка webhook от YooKassa

4. **Frontend:**
   - Компонент: `/admin/src/components/billing/pro-subscription-card.tsx`
   - Страница: `/admin/src/pages/[shop]/billing/index.tsx`
   - Хуки: `/admin/src/data/pro-subscription.ts`

## Структура базы данных

### Таблица `pro_subscriptions`

```sql
CREATE TABLE pro_subscriptions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    seller_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10, 2) DEFAULT 1990.00 COMMENT 'Стоимость подписки в ₽',
    start_date DATE NOT NULL COMMENT 'Дата начала подписки',
    end_date DATE NOT NULL COMMENT 'Дата окончания подписки (30 дней от start_date)',
    status VARCHAR(255) DEFAULT 'active' COMMENT 'active, expired, canceled',
    invoice_id BIGINT UNSIGNED NULL COMMENT 'Связь со счетом',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    
    INDEX idx_seller_status (seller_id, status),
    INDEX idx_end_date (end_date)
);
```

**Поля:**
- `id` - уникальный идентификатор подписки
- `seller_id` - ID продавца (связь с `users`)
- `amount` - стоимость подписки (по умолчанию 1990.00 ₽)
- `start_date` - дата начала подписки
- `end_date` - дата окончания подписки (start_date + 30 дней)
- `status` - статус подписки: `active`, `expired`, `canceled`
- `invoice_id` - связь со счетом (для учета оплаты)

## Модель ProSubscription

### Класс: `App\Models\ProSubscription`

**Связи:**
- `seller()` - связь с пользователем (продавцом)
- `invoice()` - связь со счетом

**Методы:**

#### `isActive(): bool`
Проверяет, активна ли подписка в данный момент.

```php
$subscription->isActive(); // true/false
```

**Логика:**
- Статус должен быть `'active'`
- Текущая дата >= `start_date`
- Текущая дата <= `end_date`

#### `isExpired(): bool`
Проверяет, истекла ли подписка.

```php
$subscription->isExpired(); // true/false
```

**Логика:**
- `end_date` < текущая дата

#### `static getActive(int $sellerId): ?self`
Получает активную подписку продавца.

```php
$subscription = ProSubscription::getActive($sellerId);
```

**Логика:**
- Ищет подписку с `seller_id = $sellerId`
- Статус = `'active'`
- `end_date` >= текущая дата
- Сортировка по `end_date DESC` (берется самая поздняя)

#### `static hasActive(int $sellerId): bool`
Проверяет, есть ли активная подписка у продавца.

```php
$hasActive = ProSubscription::hasActive($sellerId); // true/false
```

**Логика:**
- Вызывает `getActive()` и проверяет результат на `null`

## API Endpoints

### 1. Получить статус подписки

**Endpoint:** `GET /api/pro-subscription/status`

**Авторизация:** Требуется (Bearer token)

**Описание:** Возвращает информацию о текущей активной подписке PRO продавца.

**Ответ:**
```json
{
  "success": true,
  "data": {
    "has_active": true,
    "subscription": {
      "id": 1,
      "start_date": "2024-01-15",
      "end_date": "2024-02-14",
      "days_remaining": 10,
      "status": "active"
    }
  }
}
```

**Если подписки нет:**
```json
{
  "success": true,
  "data": {
    "has_active": false,
    "subscription": null
  }
}
```

### 2. Подключить подписку PRO

**Endpoint:** `POST /api/pro-subscription/subscribe`

**Авторизация:** Требуется (Bearer token)

**Описание:** Создает новую подписку PRO. Поддерживает два способа оплаты: через баланс или YooKassa.

**Тело запроса:**
```json
{
  "payment_method": "balance"  // или "yookassa"
}
```

**Валидация:**
- `payment_method` - обязательное поле, значения: `balance`, `yookassa`

**Процесс подключения:**

#### Вариант 1: Оплата через баланс

1. Проверяется наличие активной подписки
2. Проверяется достаточность средств на балансе (1990 ₽)
3. Списывается сумма с баланса продавца
4. Создается счет (`Invoice`) со статусом `paid`
5. Создается подписка (`ProSubscription`) со статусом `active`
6. Возвращается успешный ответ

**Ответ при успехе:**
```json
{
  "success": true,
  "message": "Подписка PRO успешно подключена",
  "data": {
    "subscription": {
      "id": 1,
      "seller_id": 5,
      "amount": "1990.00",
      "start_date": "2024-01-15",
      "end_date": "2024-02-14",
      "status": "active",
      "invoice_id": 10
    },
    "balance": {
      "old": 500.00,
      "new": 301.00,
      "spent": 1990.00
    }
  }
}
```

**Ошибки:**
- `400` - Подписка уже активна
- `400` - Недостаточно средств на балансе
- `500` - Ошибка при списании средств

#### Вариант 2: Оплата через YooKassa

1. Проверяется наличие активной подписки
2. Проверяется настройка YooKassa (shop_id, secret_key)
3. Создается платеж в YooKassa
4. Создается счет (`Invoice`) со статусом `pending` и `payment_id`
5. Создается подписка (`ProSubscription`) со статусом `active`
6. Возвращается URL для оплаты

**Ответ при успехе:**
```json
{
  "success": true,
  "message": "Перейдите на страницу оплаты",
  "payment_url": "https://yookassa.ru/checkout/payments/...",
  "payment_id": "2c5c8e3e-0001-5000-8000-1d5c8e3e0000",
  "subscription_id": 1
}
```

**После оплаты:**
- YooKassa отправляет webhook на `/api/invoices/webhook`
- Система обновляет счет: `status = 'paid'`, `paid_at = now()`
- Подписка остается активной (уже была создана со статусом `active`)

**Ошибки:**
- `400` - Подписка уже активна
- `500` - Платёжная система не настроена
- `500` - Ошибка при создании платежа
- `500` - Нет email для формирования чека (54-ФЗ)

### 3. Публичная проверка подписки

**Endpoint:** `GET /api/pro-subscription/check/{sellerId}`

**Авторизация:** Не требуется (публичный endpoint)

**Описание:** Проверяет наличие активной подписки у продавца. Используется для фронтенда (например, для отображения бейджа "PRO").

**Пример:**
```
GET /api/pro-subscription/check/5
```

**Ответ:**
```json
{
  "success": true,
  "data": {
    "has_active": true
  }
}
```

**Ограничения:**
- Throttle: 60 запросов в минуту

## Процесс подключения подписки

### Сценарий 1: Оплата через баланс

```
1. Продавец нажимает "Подключить подписку PRO"
   ↓
2. Frontend отправляет POST /api/pro-subscription/subscribe
   { "payment_method": "balance" }
   ↓
3. Backend проверяет:
   - Есть ли активная подписка? → Если да, возвращает ошибку
   - Достаточно ли средств на балансе? → Если нет, возвращает ошибку
   ↓
4. Backend списывает 1990 ₽ с баланса
   ↓
5. Backend создает Invoice (status = 'paid', paid_at = now())
   ↓
6. Backend создает ProSubscription (status = 'active')
   ↓
7. Backend возвращает успешный ответ
   ↓
8. Frontend показывает уведомление "Подписка PRO успешно подключена"
   ↓
9. Frontend обновляет данные подписки
```

### Сценарий 2: Оплата через YooKassa

```
1. Продавец нажимает "Подключить подписку PRO"
   ↓
2. Frontend отправляет POST /api/pro-subscription/subscribe
   { "payment_method": "yookassa" }
   ↓
3. Backend проверяет:
   - Есть ли активная подписка? → Если да, возвращает ошибку
   - Настроена ли YooKassa? → Если нет, возвращает ошибку
   ↓
4. Backend создает платеж в YooKassa
   ↓
5. Backend создает Invoice (status = 'pending', payment_id = ...)
   ↓
6. Backend создает ProSubscription (status = 'active')
   ↓
7. Backend возвращает payment_url
   ↓
8. Frontend перенаправляет на страницу оплаты YooKassa
   ↓
9. Продавец оплачивает на странице YooKassa
   ↓
10. YooKassa отправляет webhook на /api/invoices/webhook
    ↓
11. Backend обновляет Invoice (status = 'paid', paid_at = now())
    ↓
12. Backend обновляет ProSubscription (status = 'active') - уже активна
    ↓
13. YooKassa перенаправляет на returnUrl с ?subscription=success
    ↓
14. Frontend показывает уведомление "Подписка PRO успешно подключена"
    ↓
15. Frontend обновляет данные подписки
```

## Обработка webhook от YooKassa

### Endpoint: `POST /api/invoices/webhook`

**Описание:** Обрабатывает уведомления о платежах от YooKassa.

**Обрабатываемые события:**
- `payment.succeeded` - платеж успешен

**Процесс обработки:**

1. Получение webhook от YooKassa
2. Извлечение `payment_id` из данных
3. Поиск счета (`Invoice`) по `payment_id`
4. Проверка статуса платежа через API YooKassa (для безопасности)
5. Если платеж успешен (`status = 'succeeded'` и `paid = true`):
   - Обновление счета: `status = 'paid'`, `paid_at = now()`
   - Поиск подписки PRO по `invoice_id`
   - Если подписка найдена: обновление `status = 'active'` (если еще не активна)

**Код обработки:**
```php
// В InvoiceController@webhook
$proSubscription = \App\Models\ProSubscription::where('invoice_id', $invoice->id)->first();
if ($proSubscription) {
    $proSubscription->update(['status' => 'active']);
    Log::info('InvoiceController@webhook: Подписка PRO активирована', [
        'subscription_id' => $proSubscription->id,
        'seller_id' => $proSubscription->seller_id
    ]);
}
```

## Связи с другими моделями

### 1. Invoice (Счет)

**Связь:** `ProSubscription` → `Invoice` (через `invoice_id`)

**Назначение:**
- Учет оплаты подписки
- Связь с платежом YooKassa (через `payment_id` в Invoice)

**Особенности:**
- При оплате через баланс: счет создается со статусом `paid`
- При оплате через YooKassa: счет создается со статусом `pending`, обновляется через webhook

### 2. SellerBalance (Баланс продавца)

**Связь:** Косвенная (через `seller_id`)

**Назначение:**
- Списывание средств при оплате через баланс
- Проверка достаточности средств

**Методы:**
- `SellerBalance::getOrCreate($sellerId)` - получить или создать баланс
- `$balance->hasEnough($amount)` - проверить достаточность средств
- `$balance->withdraw($amount)` - списать средства

### 3. User (Пользователь)

**Связь:** `ProSubscription` → `User` (через `seller_id`)

**Назначение:**
- Определение владельца подписки
- Проверка прав доступа

## Frontend интеграция

### Компонент: `ProSubscriptionCard`

**Путь:** `/admin/src/components/billing/pro-subscription-card.tsx`

**Функционал:**
- Отображение статуса подписки
- Отображение даты окончания и оставшихся дней
- Кнопка "Подключить подписку PRO"
- Обработка оплаты через баланс

**Используемые хуки:**
- `useProSubscriptionStatusQuery()` - получение статуса
- `useSubscribeProMutation()` - подключение подписки

### Хуки: `useProSubscriptionStatusQuery` и `useSubscribeProMutation`

**Путь:** `/admin/src/data/pro-subscription.ts`

**useProSubscriptionStatusQuery:**
```typescript
const { data, isLoading, error, refetch } = useProSubscriptionStatusQuery();
```

**useSubscribeProMutation:**
```typescript
const subscribeMutation = useSubscribeProMutation();
await subscribeMutation.mutateAsync({ payment_method: 'balance' });
```

## Статусы подписки

| Статус | Описание | Условия |
|--------|----------|---------|
| `active` | Подписка активна | Статус = 'active', текущая дата между start_date и end_date |
| `expired` | Подписка истекла | end_date < текущая дата |
| `canceled` | Подписка отменена | Статус = 'canceled' (вручную) |

**Важно:** Подписка считается активной только если:
1. `status = 'active'`
2. `start_date <= текущая дата`
3. `end_date >= текущая дата`

## Логика проверки активности

### Метод `isActive()`

```php
public function isActive(): bool
{
    $now = Carbon::now();
    return $this->status === 'active' 
        && $this->start_date <= $now 
        && $this->end_date >= $now;
}
```

### Метод `getActive()`

```php
public static function getActive(int $sellerId): ?self
{
    return self::where('seller_id', $sellerId)
        ->where('status', 'active')
        ->where('end_date', '>=', Carbon::now())
        ->orderBy('end_date', 'desc')
        ->first();
}
```

**Особенности:**
- Если у продавца несколько подписок, берется самая поздняя (по `end_date`)
- Проверяется, что `end_date >= текущая дата` (не истекла)

## Примеры использования

### Пример 1: Проверка активности подписки

```php
use App\Models\ProSubscription;

$sellerId = 5;
$hasActive = ProSubscription::hasActive($sellerId);

if ($hasActive) {
    $subscription = ProSubscription::getActive($sellerId);
    echo "Подписка активна до: " . $subscription->end_date->format('d.m.Y');
} else {
    echo "Подписка не активна";
}
```

### Пример 2: Подключение подписки через баланс

```php
use App\Models\ProSubscription;
use App\Models\SellerBalance;
use App\Models\Invoice;
use Carbon\Carbon;

$user = Auth::user();
$amount = 1990.00;
$now = Carbon::now();

// Проверка баланса
$balance = SellerBalance::getOrCreate($user->id);
if (!$balance->hasEnough($amount)) {
    return response()->json(['error' => 'Недостаточно средств'], 400);
}

// Списывание
$balance->withdraw($amount);

// Создание счета
$invoice = Invoice::create([
    'seller_id' => $user->id,
    'total_amount' => $amount,
    'status' => 'paid',
    'paid_at' => now(),
]);

// Создание подписки
$subscription = ProSubscription::create([
    'seller_id' => $user->id,
    'amount' => $amount,
    'start_date' => $now,
    'end_date' => $now->copy()->addDays(30),
    'status' => 'active',
    'invoice_id' => $invoice->id,
]);
```

### Пример 3: Проверка прав доступа к функциям PRO

```php
use App\Models\ProSubscription;

function hasProAccess(int $sellerId): bool
{
    return ProSubscription::hasActive($sellerId);
}

// Использование
if (hasProAccess($user->id)) {
    // Разрешить доступ к ссылкам Ozon/WB
    // Показать расширенные функции
} else {
    // Ограничить доступ
}
```

## Настройки

### YooKassa

В файле `.env` должны быть указаны:
```env
YOOKASSA_SHOP_ID=your_shop_id
YOOKASSA_SECRET_KEY=your_secret_key
YOOKASSA_IS_TEST=true  # или false для продакшена
```

### Return URL

URL для возврата после оплаты настраивается в `ProSubscriptionController`:
```php
$returnUrl = config('app.admin_url', 'https://seller.sancan.ru') . '/dashboard/billing?subscription=success';
```

## Логирование

Все операции с подпиской логируются:

**Успешные операции:**
```php
Log::info('ProSubscriptionController@subscribe: Подписка PRO подключена через баланс', [
    'user_id' => $user->id,
    'subscription_id' => $subscription->id,
    'amount' => $amount,
    'old_balance' => $oldBalance,
    'new_balance' => $balance->balance,
]);
```

**Ошибки:**
```php
Log::error('ProSubscriptionController@subscribe: ' . $e->getMessage(), [
    'user_id' => $user->id ?? null,
    'trace' => $e->getTraceAsString()
]);
```

## Отличия от тарифных планов (Plan)

| Параметр | ProSubscription | Plan |
|----------|----------------|------|
| Назначение | Расширенные функции | Расчет за товары/плейсы |
| Стоимость | Фиксированная: 1990 ₽/30 дней | Зависит от тарифа и количества |
| Период | 30 дней | 1 месяц (календарный) |
| Оплата | Единоразовая за период | Ежемесячно |
| Связь | Независимая система | Связана с товарами/плейсами |
| Модель | `ProSubscription` | `Plan` |

## Устранение неполадок

### Подписка не активируется после оплаты

1. Проверьте логи: `storage/logs/laravel.log`
2. Проверьте, что webhook от YooKassa приходит на `/api/invoices/webhook`
3. Проверьте, что счет обновляется: `status = 'paid'`
4. Проверьте, что подписка связана со счетом: `invoice_id` в `pro_subscriptions`

### Ошибка "Недостаточно средств на балансе"

1. Проверьте баланс продавца: `SELECT * FROM seller_balances WHERE seller_id = ?`
2. Убедитесь, что баланс >= 1990.00 ₽
3. Проверьте, что метод `withdraw()` выполняется успешно

### Подписка не отображается как активная

1. Проверьте статус: `SELECT * FROM pro_subscriptions WHERE seller_id = ?`
2. Убедитесь, что `status = 'active'`
3. Проверьте даты: `start_date <= NOW()` и `end_date >= NOW()`
4. Проверьте метод `getActive()` - он должен находить подписку

## Дальнейшее развитие

Возможные улучшения:
1. Автоматическое продление подписки
2. Уведомления о скором истечении подписки
3. Скидки и промокоды
4. Разные периоды подписки (7, 14, 30, 90 дней)
5. История подписок
6. Экспорт данных о подписках
7. Интеграция с другими платежными системами

