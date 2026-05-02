# Email Notification System

Полноценная система email уведомлений для Marvel E-commerce API.

## Обзор

Система включает в себя:
- **EmailController** - REST API для отправки email уведомлений
- **EmailService** - централизованный сервис для управления отправкой
- **Улучшенные Notifications** - обновленные уведомления для разных типов получателей
- **Email шаблоны** - красивые HTML шаблоны для всех типов уведомлений
- **Консольные команды** - для тестирования и управления

## API Endpoints

### Публичные маршруты

#### Отправка контактной формы
```http
POST /api/email/contact
Content-Type: application/json

{
    "subject": "Вопрос о заказе",
    "name": "Иван Иванов",
    "email": "ivan@example.com",
    "description": "Текст сообщения"
}
```

#### Сброс пароля
```http
POST /api/email/password-reset
Content-Type: application/json

{
    "email": "user@example.com"
}
```

### Админские маршруты (требуют авторизации super_admin)

#### Тестирование конфигурации email
```http
POST /api/email/test-configuration
Authorization: Bearer {token}
```

#### Массовая рассылка
```http
POST /api/email/bulk
Authorization: Bearer {token}
Content-Type: application/json

{
    "user_ids": [1, 2, 3],
    "subject": "Важное уведомление",
    "message": "Текст сообщения",
    "template": "emails.custom"
}
```

#### Уведомления о заказах

**Клиенту:**
```http
POST /api/email/order/customer
Authorization: Bearer {token}
Content-Type: application/json

{
    "order_id": 123,
    "notification_type": "created"
}
```

**Владельцу магазина:**
```http
POST /api/email/order/store-owner
Authorization: Bearer {token}
Content-Type: application/json

{
    "order_id": 123,
    "notification_type": "created"
}
```

**Админам:**
```http
POST /api/email/order/admin
Authorization: Bearer {token}
Content-Type: application/json

{
    "order_id": 123,
    "notification_type": "created"
}
```

#### Уведомления о товарах
```http
POST /api/email/product
Authorization: Bearer {token}
Content-Type: application/json

{
    "product_id": 456,
    "notification_type": "approved",
    "recipient_type": "vendor"
}
```

#### Уведомления о платежах
```http
POST /api/email/payment
Authorization: Bearer {token}
Content-Type: application/json

{
    "order_id": 123,
    "notification_type": "successful",
    "recipient_type": "customer"
}
```

## Использование EmailService

### В контроллерах

```php
use Marvel\Services\EmailService;

class SomeController extends Controller
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    public function someAction()
    {
        // Отправка уведомления о заказе всем заинтересованным сторонам
        $results = $this->emailService->sendOrderEventNotifications($order, 'created');
        
        // Отправка уведомления о товаре
        $this->emailService->sendProductNotification($product, 'approved', 'vendor');
        
        // Массовая рассылка
        $sentCount = $this->emailService->sendBulkEmail(
            [1, 2, 3], 
            'Тема', 
            'Сообщение'
        );
    }
}
```

### В Listeners

```php
use Marvel\Services\EmailService;

class SomeListener
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    public function handle(SomeEvent $event)
    {
        // Автоматическая отправка уведомлений при событии
        $this->emailService->sendOrderEventNotifications($event->order, 'created');
    }
}
```

## Консольные команды

### Тестирование конфигурации email
```bash
php artisan email:test --type=config --email=admin@example.com
```

### Тестирование уведомлений о заказах
```bash
php artisan email:test --type=order --order-id=123
```

### Тестирование уведомлений о товарах
```bash
php artisan email:test --type=product --product-id=456
```

### Тестирование уведомлений о платежах
```bash
php artisan email:test --type=payment --order-id=123
```

### Тестирование массовой рассылки
```bash
php artisan email:test --type=bulk --user-ids=1,2,3
```

## Типы уведомлений

### Заказы
- `created` - заказ создан
- `processed` - заказ обработан
- `delivered` - заказ доставлен
- `cancelled` - заказ отменен
- `status_changed` - статус заказа изменен

### Товары
- `approved` - товар одобрен
- `rejected` - товар отклонен

### Платежи
- `successful` - платеж успешен
- `failed` - платеж неудачен

### Получатели
- `customer` - клиент
- `vendor` - продавец/владелец магазина
- `admin` - администратор

## Email шаблоны

Все шаблоны находятся в `resources/views/emails/`:

- `order/order-received.blade.php` - уведомление о новом заказе
- `order/placed.blade.php` - подтверждение заказа клиенту
- `order/order-cancelled.blade.php` - отмена заказа
- `order/order-delivered.blade.php` - доставка заказа
- `order/order-status-changed.blade.php` - изменение статуса
- `payment/payment-successful.blade.php` - успешный платеж
- `payment/payment-failed.blade.php` - неудачный платеж
- `product/product-approved.blade.php` - товар одобрен
- `product/product-rejected.blade.php` - товар отклонен
- `custom.blade.php` - кастомное сообщение
- `test-email.blade.php` - тестовое письмо

## Конфигурация

### Настройки в .env
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"

# Админский email для уведомлений
SHOP_ADMIN_EMAIL=admin@example.com
```

### Настройки в config/shop.php
```php
'admin_email' => env('SHOP_ADMIN_EMAIL', 'admin@example.com'),
'dashboard_url' => env('SHOP_DASHBOARD_URL', 'https://admin.example.com'),
```

## Автоматические уведомления

Система автоматически отправляет уведомления при следующих событиях:

1. **Создание заказа** - клиенту, владельцу магазина, админам
2. **Изменение статуса заказа** - клиенту, владельцу магазина, админам
3. **Обработка заказа** - клиенту, владельцу магазина, админам
4. **Доставка заказа** - клиенту, владельцу магазина, админам
5. **Отмена заказа** - клиенту, владельцу магазина, админам
6. **Успешный платеж** - клиенту, владельцу магазина, админам
7. **Неудачный платеж** - клиенту, владельцу магазина, админам
8. **Одобрение товара** - продавцу, админам
9. **Отклонение товара** - продавцу, админам

## Логирование

Все email операции логируются в Laravel log:
- Успешные отправки
- Ошибки отправки
- Детали конфигурации

## Безопасность

- Все админские маршруты защищены middleware `auth:api` и `role:super_admin`
- Валидация всех входящих данных
- Обработка ошибок и исключений
- Логирование всех операций

## Расширение системы

### Добавление нового типа уведомления

1. Создайте Notification класс в `packages/marvel/src/Notifications/`
2. Создайте email шаблон в `resources/views/emails/`
3. Добавьте метод в EmailService
4. Добавьте маршрут в EmailController
5. Обновите EventServiceProvider для автоматической отправки

### Добавление нового получателя

1. Обновите метод `getAdminUsers()` в EmailService
2. Добавьте логику в соответствующие методы отправки
3. Обновите email шаблоны при необходимости

## Troubleshooting

### Email не отправляются
1. Проверьте конфигурацию в .env
2. Запустите `php artisan email:test --type=config`
3. Проверьте логи Laravel
4. Убедитесь, что очередь работает

### Ошибки в шаблонах
1. Проверьте синтаксис Blade
2. Убедитесь, что все переменные переданы
3. Проверьте локализацию

### Проблемы с очередью
1. Запустите worker: `php artisan queue:work`
2. Проверьте конфигурацию очереди в .env
3. Очистите неудачные задачи: `php artisan queue:failed:clear`


