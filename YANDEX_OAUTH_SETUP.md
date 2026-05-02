# Интеграция Яндекс OAuth 2.0 для Laravel

Полная инструкция по настройке и использованию авторизации через Яндекс OAuth 2.0 в Laravel проекте.

## 📋 Содержание

1. [Установка пакетов](#установка-пакетов)
2. [Конфигурация](#конфигурация)
3. [Регистрация приложения в Яндексе](#регистрация-приложения-в-яндексе)
4. [Настройка .env](#настройка-env)
5. [Регистрация провайдера](#регистрация-провайдера)
6. [Использование](#использование)
7. [Тестирование](#тестирование)
8. [Режим stateless (API/SPA)](#режим-stateless-apispa)
9. [Безопасность](#безопасность)

## 📦 Архитектура проекта

**Важно:** Контроллер размещен в пакете **Marvel** (`packages/marvel/src/Http/Controllers/YandexAuthController.php`) для единообразия с остальными контроллерами проекта.

- **Веб-авторизация** (редиректы): `YandexAuthController` в Marvel
- **API-авторизация** (токены): метод `socialLogin` в `UserController` (Marvel) - уже поддерживает Яндекс

---

## 🔧 Установка пакетов

### Шаг 1: Установка SocialiteProviders/Yandex

```bash
cd pixer-api
composer require socialiteproviders/yandex
```

**Примечание:** Пакет `laravel/socialite` уже установлен в проекте (версия 5.6.1), поэтому дополнительная установка не требуется.

### Шаг 2: Очистка кэша конфигурации

```bash
php artisan config:clear
php artisan cache:clear
```

---

## ⚙️ Конфигурация

### 1. Регистрация провайдера в EventServiceProvider

Откройте файл `app/Providers/EventServiceProvider.php` и добавьте регистрацию провайдера:

```php
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        // ... существующие слушатели
        
        // Регистрация провайдера Яндекс OAuth
        SocialiteWasCalled::class => [
            'SocialiteProviders\\Yandex\\YandexExtendSocialite@handle',
        ],
    ];

    // ... остальной код
}
```

**Важно:** Если в проекте используется пакет Marvel, который может иметь свой EventServiceProvider, проверьте файл `packages/marvel/src/Providers/EventServiceProvider.php` и добавьте регистрацию туда, если необходимо.

### 2. Обновление config/services.php

Конфигурация уже добавлена в оба файла:
- `config/services.php`
- `packages/marvel/config/services.php`

Проверьте, что в файлах есть следующая секция:

```php
'yandex' => [
    'client_id' => env('YANDEX_CLIENT_ID'),
    'client_secret' => env('YANDEX_CLIENT_SECRET'),
    'redirect' => env('YANDEX_REDIRECT_URI', 'https://sancan.ru/auth/yandex/callback'),
],
```

---

## 🌐 Регистрация приложения в Яндексе

### Шаг 1: Создание приложения

1. Перейдите на страницу [OAuth приложений Яндекса](https://oauth.yandex.ru/)
2. Нажмите "Зарегистрировать новое приложение"
3. Заполните форму:
   - **Название приложения:** SanCan (или любое другое)
   - **Платформы:** Web-сервисы
   - **Callback URI #1:** `https://sancan.ru/auth/yandex/callback`
   - **Права доступа:** 
     - `login:email` - доступ к email
     - `login:info` - доступ к информации о пользователе
     - `login:avatar` - доступ к аватару

### Шаг 2: Получение ключей

После создания приложения вы получите:
- **ID приложения (Client ID):** `5abe5fed5ed3406593988a1dc7101159`
- **Пароль (Client Secret):** `03271af05e034be38a2d2be5c69038aa`

**Важно:** Эти ключи уже предоставлены в задании, но в продакшене их нужно хранить в безопасности.

---

## 🔐 Настройка .env

Добавьте следующие переменные в файл `.env`:

```env
# Yandex OAuth 2.0 Configuration
YANDEX_CLIENT_ID=5abe5fed5ed3406593988a1dc7101159
YANDEX_CLIENT_SECRET=03271af05e034be38a2d2be5c69038aa
YANDEX_REDIRECT_URI=https://sancan.ru/auth/yandex/callback
```

**Важно:** 
- Не коммитьте файл `.env` в Git
- Используйте разные ключи для development и production
- Храните `CLIENT_SECRET` в безопасности

---

## 📝 Регистрация провайдера

Если вы еще не зарегистрировали провайдер в `EventServiceProvider`, выполните следующие шаги:

### Проверка существующего EventServiceProvider

Проверьте файл `app/Providers/EventServiceProvider.php`:

```bash
cat app/Providers/EventServiceProvider.php
```

Если в файле нет регистрации `SocialiteWasCalled`, добавьте её (см. раздел "Конфигурация" выше).

### Альтернативный способ: через config/app.php

Если у вас нет доступа к EventServiceProvider, можно зарегистрировать провайдер через конфигурацию, но это менее предпочтительный способ.

---

## 🚀 Использование

### Маршруты

Маршруты уже добавлены в `routes/web.php`:

```php
// Перенаправление на авторизацию
GET /auth/yandex

// Callback от Яндекса
GET /auth/yandex/callback

// Получение данных пользователя (требует авторизации)
GET /auth/yandex/user
```

### Frontend: Ссылка на авторизацию

Добавьте кнопку или ссылку на странице входа:

```html
<!-- HTML вариант -->
<a href="/auth/yandex" class="btn btn-yandex">
    <img src="/images/yandex-logo.svg" alt="Yandex">
    Войти через Яндекс
</a>
```

```tsx
// React/Next.js вариант
<Link href="/auth/yandex">
  <button className="btn-yandex">
    <img src="/images/yandex-logo.svg" alt="Yandex" />
    Войти через Яндекс
  </button>
</Link>
```

```javascript
// JavaScript вариант
document.getElementById('yandex-login').addEventListener('click', function() {
    window.location.href = '/auth/yandex';
});
```

### Пример ответа после успешной авторизации

#### JSON ответ (для API/SPA):

```json
{
  "success": true,
  "message": "Авторизация через Яндекс успешна",
  "user": {
    "id": 123,
    "email": "user@example.com",
    "name": "Иван Иванов",
    "yandex_id": "1234567890",
    "yandex_login": "ivan.ivanov",
    "real_name": "Иван Иванов",
    "avatar": "https://avatars.yandex.net/get-yapic/.../islands-200",
    "created_at": "2024-01-01T12:00:00.000000Z",
    "updated_at": "2024-01-01T12:00:00.000000Z"
  },
  "session_id": "abc123def456..."
}
```

#### HTML ответ (для обычных веб-запросов):

Пользователь будет перенаправлен на главную страницу (`/`) с сообщением об успешной авторизации.

---

## 🧪 Тестирование

### Пошаговая инструкция по проверке

1. **Проверка конфигурации:**
   ```bash
   php artisan config:show services.yandex
   ```
   Должны отображаться значения из `.env`.

2. **Проверка маршрутов:**
   ```bash
   php artisan route:list | grep yandex
   ```
   Должны быть видны три маршрута:
   - `GET /auth/yandex`
   - `GET /auth/yandex/callback`
   - `GET /auth/yandex/user`

3. **Тестирование в браузере:**
   
   a. Откройте в браузере: `https://sancan.ru/auth/yandex`
   
   b. Должно произойти перенаправление на страницу авторизации Яндекса
   
   c. Войдите в аккаунт Яндекса
   
   d. Разрешите доступ приложению
   
   e. Должно произойти перенаправление на `https://sancan.ru/auth/yandex/callback`
   
   f. После обработки callback вы должны быть авторизованы

4. **Проверка данных пользователя:**
   
   После успешной авторизации откройте: `https://sancan.ru/auth/yandex/user`
   
   Должны вернуться данные авторизованного пользователя в формате JSON.

5. **Проверка логов:**
   
   ```bash
   tail -f storage/logs/laravel.log
   ```
   
   При успешной авторизации должны быть записи:
   - `Yandex OAuth callback success`
   - Данные пользователя (без чувствительной информации)

### Тестирование через cURL

```bash
# Получение URL авторизации (должен вернуть редирект)
curl -I https://sancan.ru/auth/yandex

# Проверка данных пользователя (требует авторизации)
curl -X GET https://sancan.ru/auth/yandex/user \
  -H "Cookie: laravel_session=your_session_id"
```

### Возможные ошибки и решения

#### Ошибка: "Invalid redirect URI"
- **Причина:** Callback URI в приложении Яндекса не совпадает с `YANDEX_REDIRECT_URI` в `.env`
- **Решение:** Проверьте, что в настройках приложения на `oauth.yandex.ru` указан правильный Callback URI

#### Ошибка: "Invalid client"
- **Причина:** Неверный `CLIENT_ID` или `CLIENT_SECRET`
- **Решение:** Проверьте значения в `.env` и перезапустите сервер

#### Ошибка: "Class 'SocialiteProviders\Yandex\YandexExtendSocialite' not found"
- **Причина:** Пакет `socialiteproviders/yandex` не установлен
- **Решение:** Выполните `composer require socialiteproviders/yandex` и `composer dump-autoload`

---

## 🔄 Режим stateless (API/SPA)

Если ваше приложение работает в режиме API или SPA (Single Page Application), где нет сессий, нужно внести следующие изменения:

### 1. Обновление контроллера для stateless режима

В методе `callback` контроллера `YandexAuthController` уже есть поддержка JSON ответов. Для полноценной работы в stateless режиме:

1. **Использование JWT токенов вместо сессий:**
   
   Вместо `Auth::login($user)` используйте создание токена:
   
   ```php
   // В методе callback, после создания/обновления пользователя
   $token = $user->createToken('yandex-oauth-token')->plainTextToken;
   
   return response()->json([
       'success' => true,
       'token' => $token,
       'token_type' => 'Bearer',
       'user' => $userData
   ]);
   ```

2. **Обновление маршрутов для API:**
   
   Добавьте маршруты в `routes/api.php`:
   
   ```php
   Route::prefix('auth/yandex')->group(function () {
       Route::get('/', [App\Http\Controllers\YandexAuthController::class, 'redirect']);
       Route::get('/callback', [App\Http\Controllers\YandexAuthController::class, 'callback']);
   });
   ```

3. **Настройка CORS:**
   
   Убедитесь, что CORS настроен правильно для вашего фронтенда в `config/cors.php`.

### 2. Frontend интеграция для SPA

```javascript
// Пример для React/Vue/Angular
async function loginWithYandex() {
    // Открываем окно авторизации
    const authWindow = window.open(
        'https://sancan.ru/auth/yandex',
        'yandex-auth',
        'width=600,height=700'
    );
    
    // Слушаем сообщения от callback страницы
    window.addEventListener('message', async (event) => {
        if (event.origin !== 'https://sancan.ru') return;
        
        if (event.data.type === 'YANDEX_AUTH_SUCCESS') {
            const { token, user } = event.data;
            
            // Сохраняем токен
            localStorage.setItem('auth_token', token);
            
            // Обновляем состояние приложения
            // ...
            
            authWindow.close();
        }
    });
}
```

### 3. Callback страница для SPA

Создайте простую HTML страницу `public/yandex-callback.html`:

```html
<!DOCTYPE html>
<html>
<head>
    <title>Авторизация через Яндекс</title>
</head>
<body>
    <script>
        // Получаем данные из URL или делаем запрос к API
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('token');
        
        if (token) {
            // Отправляем сообщение родительскому окну
            window.opener.postMessage({
                type: 'YANDEX_AUTH_SUCCESS',
                token: token
            }, 'https://sancan.ru');
            
            window.close();
        }
    </script>
</body>
</html>
```

---

## 🔒 Безопасность

### Хранение CLIENT_SECRET

1. **Никогда не коммитьте `.env` в Git:**
   - Убедитесь, что `.env` в `.gitignore`
   - Используйте `.env.example` для документации (без реальных ключей)

2. **Использование переменных окружения на сервере:**
   - На production сервере храните ключи в переменных окружения системы
   - Используйте секреты в CI/CD системах (GitHub Secrets, GitLab CI Variables)

3. **Ротация ключей:**
   - Периодически обновляйте `CLIENT_SECRET`
   - При компрометации немедленно отзовите старые ключи

### Отзыв скомпрометированных ключей

Если ключи были скомпрометированы:

1. **Немедленно отзовите ключи:**
   - Перейдите на [oauth.yandex.ru](https://oauth.yandex.ru/)
   - Найдите ваше приложение
   - Нажмите "Удалить" или "Отозвать доступ"

2. **Создайте новые ключи:**
   - Создайте новое приложение или сгенерируйте новые ключи
   - Обновите значения в `.env` на всех серверах

3. **Проверьте логи:**
   - Проверьте логи приложения на подозрительную активность
   - Проверьте доступы пользователей

### Дополнительные меры безопасности

1. **Валидация redirect URI:**
   - Всегда проверяйте, что redirect URI соответствует ожидаемому
   - Используйте whitelist разрешенных redirect URI

2. **Rate limiting:**
   - Добавьте rate limiting для маршрутов авторизации
   - Защитите от brute force атак

3. **HTTPS:**
   - Всегда используйте HTTPS в production
   - Яндекс требует HTTPS для callback URI

4. **Логирование:**
   - Логируйте все попытки авторизации
   - Мониторьте подозрительную активность

---

## 📚 Дополнительные ресурсы

- [Документация Яндекс OAuth 2.0](https://yandex.ru/dev/id/doc/ru/)
- [Документация Laravel Socialite](https://laravel.com/docs/socialite)
- [Документация SocialiteProviders/Yandex](https://socialiteproviders.com/Yandex/)

---

## ✅ Чеклист развертывания

- [ ] Установлен пакет `socialiteproviders/yandex`
- [ ] Зарегистрирован провайдер в `EventServiceProvider`
- [ ] Обновлен `config/services.php` (оба файла)
- [ ] Добавлены переменные в `.env`
- [ ] Создано приложение на `oauth.yandex.ru`
- [ ] Настроен правильный Callback URI
- [ ] Добавлены маршруты в `routes/web.php`
- [ ] Создан контроллер `YandexAuthController`
- [ ] Протестирована авторизация в браузере
- [ ] Проверены логи на наличие ошибок
- [ ] Настроена безопасность (HTTPS, rate limiting)

---

## 🆘 Поддержка

При возникновении проблем:

1. Проверьте логи: `storage/logs/laravel.log`
2. Проверьте конфигурацию: `php artisan config:show services.yandex`
3. Очистите кэш: `php artisan config:clear && php artisan cache:clear`
4. Проверьте документацию Яндекса и SocialiteProviders

---

**Готово!** Интеграция Яндекс OAuth 2.0 настроена и готова к использованию. 🎉



