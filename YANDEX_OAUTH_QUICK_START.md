# Яндекс OAuth 2.0 - Быстрый старт

## 🚀 Установка за 5 минут

### 1. Установите пакет

```bash
cd pixer-api
composer require socialiteproviders/yandex
```

### 2. Добавьте в .env

```env
YANDEX_CLIENT_ID=5abe5fed5ed3406593988a1dc7101159
YANDEX_CLIENT_SECRET=03271af05e034be38a2d2be5c69038aa
YANDEX_REDIRECT_URI=https://sancan.ru/auth/yandex/callback
```

### 3. Очистите кэш

```bash
php artisan config:clear
php artisan cache:clear
```

### 4. Готово! Тестируйте

Откройте в браузере: `https://sancan.ru/auth/yandex`

---

## 📝 Что уже сделано

✅ Контроллер `YandexAuthController` создан  
✅ Маршруты добавлены в `routes/web.php`  
✅ Конфигурация добавлена в `config/services.php`  
✅ Провайдер зарегистрирован в `EventServiceProvider`  

---

## 🔗 Маршруты

- `GET /auth/yandex` - Перенаправление на авторизацию
- `GET /auth/yandex/callback` - Обработка callback
- `GET /auth/yandex/user` - Данные пользователя (требует авторизации)

---

## 📚 Полная документация

См. файл `YANDEX_OAUTH_SETUP.md` для подробной инструкции.



