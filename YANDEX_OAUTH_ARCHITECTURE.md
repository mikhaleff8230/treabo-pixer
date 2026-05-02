# 🏗️ Архитектура Яндекс OAuth в проекте

## 📦 Структура файлов

### Контроллеры

#### 1. Веб-авторизация (редиректы)
- **Файл:** `packages/marvel/src/Http/Controllers/YandexAuthController.php`
- **Назначение:** Обработка веб-авторизации через редиректы
- **Маршруты:**
  - `GET /auth/yandex` - перенаправление на Яндекс
  - `GET /auth/yandex/callback` - обработка callback
  - `GET /auth/yandex/user` - данные пользователя

#### 2. API-авторизация (токены)
- **Файл:** `packages/marvel/src/Http/Controllers/UserController.php`
- **Метод:** `socialLogin()`
- **Маршрут:** `POST /api/social-login-token` (уже существует в Marvel Routes)
- **Назначение:** Авторизация через токен для API/SPA приложений

## 🔄 Почему в Marvel?

1. **Единообразие:** Все контроллеры авторизации находятся в Marvel
2. **Интеграция:** Использует модели и репозитории Marvel (`User`, `Profile`, `Permission`)
3. **Совместимость:** Работает с существующей системой прав и провайдеров
4. **Расширяемость:** Легко добавить поддержку других OAuth провайдеров

## 📊 Сравнение подходов

| Аспект | App/ | Marvel/ |
|--------|------|---------|
| **Расположение** | `app/Http/Controllers/` | `packages/marvel/src/Http/Controllers/` |
| **Использование** | Кастомная логика проекта | Стандартный eCommerce функционал |
| **Примеры** | `HomeController`, `InvoiceController` | `UserController`, `ProductController` |
| **OAuth** | ❌ Не используется | ✅ Используется (`socialLogin`) |

## ✅ Решение

**Контроллер размещен в Marvel**, так как:
- OAuth - это часть системы авторизации (как `UserController`)
- Использует модели Marvel (`User`, `Profile`)
- Интегрируется с системой прав Marvel (`Permission`)
- Соответствует архитектуре проекта

## 🔗 Связи

```
YandexAuthController (Marvel)
    ↓
UserController::socialLogin() (Marvel) - API-режим
    ↓
User Model (Marvel)
    ↓
Profile Model (Marvel)
    ↓
Permission System (Marvel)
```

## 📝 Использование

### Веб-авторизация (редиректы)
```php
// Маршрут: GET /auth/yandex
Route::get('/auth/yandex', [Marvel\Http\Controllers\YandexAuthController::class, 'redirect']);
```

### API-авторизация (токены)
```php
// Маршрут: POST /api/social-login-token
// Уже существует в packages/marvel/src/Rest/Routes.php
Route::post('/social-login-token', [UserController::class, 'socialLogin']);

// Использование:
POST /api/social-login-token
{
    "provider": "yandex",
    "access_token": "ya29.a0AfH6SMB..."
}
```

## 🎯 Итог

Все файлы OAuth находятся в **Marvel пакете** для единообразия и правильной архитектуры проекта.

