# Логика авторизации и решение проблемы 401 Unauthorized

## Проблема

После реализации SMS-верификации и PIN-кода авторизация полностью перестала работать. Все запросы к защищенным эндпоинтам (особенно `/me`) возвращали `401 Unauthorized`, даже при наличии валидного токена в cookie.

### Симптомы

- Множественные 401 ошибки в консоли браузера при загрузке страницы
- Запросы к `/me` и `/wishlists/in_wishlist/{id}` возвращали 401
- Токен присутствовал в cookie, но не распознавался бэкендом
- Проблема возникала сразу после обновления страницы, без попытки входа

## Диагностика

### Этап 1: Проверка фронтенда

Изначально проблема была связана с фронтендом:
- Проверены компоненты `useAuth`, `useMe`, `http-client.ts`
- Проверена синхронизация токена между Jotai и cookies
- Проверена логика отмены запросов без токена

**Результат:** Фронтенд работал корректно, токен передавался в заголовке `Authorization: Bearer <token>`.

### Этап 2: Проверка бэкенда

Проверка показала:
- ✅ Конфигурация guard `api` использует драйвер `sanctum`
- ✅ Роуты `/me` и `/token` существуют
- ✅ `useMustVerifyEmail` выключен (не блокирует запросы)
- ✅ Токены создаются и сохраняются в БД
- ❌ Токен не распознавался Sanctum middleware

### Этап 3: Анализ токена

Создан скрипт `check-token.php` для проверки токена из cookie:

**Обнаружено:**
1. Токен был **зашифрован** в cookie (Laravel автоматически шифрует все cookie)
2. После расшифровки токен имел **неправильный формат**: `{hash}|{id}|{token}`
3. Sanctum ожидает формат: `{id}|{token}`
4. Токен найден в БД по ID, но `PersonalAccessToken::findToken()` не мог его найти из-за неправильного формата

## Решение

### 1. Исключение токена из шифрования cookie

**Файл:** `pixer-api/app/Http/Middleware/EncryptCookies.php`

```php
protected $except = [
    'pixer-auth-token', // Не шифруем токен авторизации - он уже защищен Bearer token
];
```

**Причина:** Laravel автоматически шифрует все cookie, но токен авторизации не должен быть зашифрован, так как он уже защищен через Bearer token в заголовке Authorization.

### 2. Middleware для исправления формата токена

**Файл:** `pixer-api/app/Http/Middleware/FixSanctumToken.php` (создан)

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware для исправления формата токена Sanctum
 * Исправляет формат {hash}|{id}|{token} на {id}|{token}
 */
class FixSanctumToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        
        if ($token) {
            // Проверяем формат токена
            $parts = explode('|', $token);
            
            // Если формат {hash}|{id}|{token}, исправляем на {id}|{token}
            if (count($parts) === 3) {
                $correctToken = $parts[1] . '|' . $parts[2];
                
                // Заменяем токен в заголовке
                $request->headers->set('Authorization', 'Bearer ' . $correctToken);
            }
        }
        
        return $next($request);
    }
}
```

**Причина:** Токен из cookie имел неправильный формат из-за предыдущего шифрования. Middleware автоматически исправляет формат перед проверкой Sanctum.

**Регистрация:** `pixer-api/app/Http/Kernel.php`

```php
'api' => [
    \App\Http\Middleware\FixSanctumToken::class, // Исправление формата токена
    'throttle:api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```

### 3. Конфигурация auth guard

**Файл:** `pixer-api/packages/marvel/config/auth.php`

```php
'guards' => [
    'api' => [
        'driver'   => 'sanctum',
        'provider' => 'users',
        'hash'     => false,
    ],
    'sanctum' => [
        'driver'   => 'sanctum',
        'provider' => 'users',
    ],
],
```

**Важно:** Конфигурация из `packages/marvel/config/auth.php` полностью переопределяет основную конфигурацию через `ShopServiceProvider`.

### 4. Использование auth:sanctum middleware

**Файл:** `pixer-api/packages/marvel/src/Rest/Routes.php`

```php
Route::group(['middleware' => ['can:' . Permission::CUSTOMER, 'auth:sanctum', 'email.verified']], function () {
    Route::get('me', [UserController::class, 'me']);
    // ...
});
```

**Причина:** Использование `auth:sanctum` вместо `auth:api` обеспечивает правильную обработку Bearer токенов через Sanctum.

## Как работает авторизация сейчас

### Фронтенд (Next.js/React)

#### 1. Сохранение токена

**Файл:** `shop/src/data/client/token.utils.ts`

```typescript
export function setAuthToken(token: string) {
  Cookies.set(AUTH_TOKEN_KEY, token, { expires: 1 });
}

export function getAuthToken() {
  if (typeof window === 'undefined') {
    return null;
  }
  return Cookies.get(AUTH_TOKEN_KEY);
}
```

- Токен сохраняется в cookie с ключом `pixer-auth-token`
- Cookie не шифруется (бэкенд исключил из шифрования)
- Срок действия: 1 день

#### 2. Синхронизация с Jotai

**Файл:** `shop/src/store/auth-store.ts`

```typescript
export const authTokenAtom = atom<string | null>((get) => {
  const internal = get(authTokenAtomInternal);
  // Синхронизация с cookie
  if (internal === null && typeof window !== 'undefined') {
    return getAuthToken();
  }
  return internal;
});
```

- Jotai атом синхронизируется с cookie
- При чтении проверяет cookie, если атом пустой
- При записи обновляет и атом, и cookie

#### 3. HTTP Client

**Файл:** `shop/src/data/client/http-client.ts`

```typescript
Axios.interceptors.request.use(
  (config) => {
    const token = getAuthToken();
    const url = config.url || '';
    
    // Публичные эндпоинты
    const publicEndpoints = [
      '/categories', '/products', '/token', '/register', 
      '/send-otp-code', '/verify-otp-code', '/otp-login', 
      '/verify-pin-code', '/social-login-token',
    ];
    const isPublicEndpoint = publicEndpoints.some((ep) => url.includes(ep));
    
    // Отменяем запросы к защищенным эндпоинтам без токена
    if (!isPublicEndpoint && !token) {
      const source = axios.CancelToken.source();
      source.cancel('No token available - request cancelled');
      config.cancelToken = source.token;
      return Promise.reject(new axios.Cancel('Request cancelled: No token available'));
    }
    
    // Добавляем токен в заголовок
    config.headers = {
      ...config.headers,
      Authorization: token ? `Bearer ${token}` : '',
    };
    return config;
  }
);
```

**Логика:**
1. Читает токен из cookie через `getAuthToken()`
2. Для защищенных эндпоинтов отменяет запрос, если токена нет
3. Добавляет токен в заголовок `Authorization: Bearer <token>`

#### 4. React Query Hook

**Файл:** `shop/src/data/user.ts`

```typescript
export function useMe() {
  const { isAuthorized, getToken } = useAuth();
  const queryClient = useQueryClient();

  const token = getToken();
  const hasToken = Boolean(token);
  const shouldFetch = hasToken && isAuthorized;

  const { data, isLoading, error } = useQuery<User, Error>(
    [API_ENDPOINTS.USERS_ME, token],
    client.users.me,
    {
      enabled: shouldFetch,
      retry: false,
      staleTime: 5 * 60 * 1000,
      cacheTime: 10 * 60 * 1000,
      refetchOnMount: false,
      refetchOnWindowFocus: false,
      refetchOnReconnect: false,
      onError: (err: any) => {
        if (axios.isCancel && axios.isCancel(err)) {
          return; // Игнорируем отмененные запросы
        }
        if (err?.response?.status === 401) {
          unauthorize();
          queryClient.removeQueries([API_ENDPOINTS.USERS_ME]);
        }
      },
    }
  );

  return {
    me: data,
    isLoading: shouldFetch ? isLoading : false,
    error,
    isAuthorized: hasToken && isAuthorized,
  };
}
```

**Логика:**
- Запрос выполняется только если есть токен и пользователь авторизован
- Игнорирует отмененные запросы (когда нет токена)
- При 401 ошибке очищает токен и кэш
- Не делает лишних запросов при монтировании/фокусе окна

### Бэкенд (Laravel)

#### 1. Получение токена из запроса

**Middleware:** `FixSanctumToken`

1. Извлекает токен из заголовка `Authorization: Bearer <token>`
2. Проверяет формат токена
3. Если формат `{hash}|{id}|{token}`, исправляет на `{id}|{token}`
4. Заменяет токен в заголовке запроса

#### 2. Проверка токена через Sanctum

**Guard:** `auth:sanctum`

1. Sanctum middleware извлекает токен из заголовка `Authorization`
2. Использует `PersonalAccessToken::findToken($token)` для поиска в БД
3. Проверяет формат: должен быть `{id}|{token}`
4. Ищет запись в таблице `personal_access_tokens`:
   - По ID (первая часть до `|`)
   - Сравнивает хеш токена (вторая часть после `|`)
5. Если токен найден и валиден:
   - Загружает связанного пользователя (`tokenable`)
   - Устанавливает пользователя в запрос через `$request->setUserResolver()`
   - Обновляет `last_used_at` в БД

#### 3. Проверка прав доступа

**Middleware:** `auth:sanctum`, `can:customer`, `email.verified`

1. `auth:sanctum` - проверяет наличие валидного токена
2. `can:customer` - проверяет разрешение `customer` у пользователя
3. `email.verified` - проверяет подтверждение email (если `useMustVerifyEmail = true`)

#### 4. Возврат данных пользователя

**Контроллер:** `UserController::me()`

```php
public function me(Request $request)
{
    try {
        $user = $request->user(); // Получает пользователя из запроса (установлен Sanctum)

        if (isset($user)) {
            return $this->repository->with(['profile', 'wallet', 'address', 'shops.balance', 'managed_shop.balance'])->find($user->id);
        }
        throw new AuthorizationException(NOT_AUTHORIZED);
    } catch (MarvelException $e) {
        throw new MarvelException(NOT_AUTHORIZED);
    }
}
```

## Поток авторизации

### 1. Вход пользователя

```
Пользователь вводит email/password
    ↓
POST /api/token
    ↓
UserController::token()
    ↓
Создание токена: $user->createToken('auth_token')->plainTextToken
    ↓
Формат токена: {id}|{token} (например: "1503|Wc7ziBiSKWYCVAJ...")
    ↓
Возврат токена фронтенду
    ↓
setAuthToken(token) - сохранение в cookie
```

### 2. Запрос к защищенному эндпоинту

```
Компонент вызывает useMe()
    ↓
useMe() проверяет наличие токена
    ↓
HTTP Client добавляет Authorization: Bearer {token}
    ↓
GET /api/me
    ↓
FixSanctumToken middleware:
    - Извлекает токен из заголовка
    - Если формат {hash}|{id}|{token}, исправляет на {id}|{token}
    - Заменяет токен в заголовке
    ↓
auth:sanctum middleware:
    - Извлекает токен из заголовка
    - PersonalAccessToken::findToken($token)
    - Находит запись в БД
    - Загружает пользователя
    - Устанавливает $request->user()
    ↓
can:customer middleware:
    - Проверяет разрешение у пользователя
    ↓
email.verified middleware:
    - Проверяет подтверждение email (если включено)
    ↓
UserController::me():
    - $request->user() возвращает пользователя
    - Загружает связанные данные (profile, wallet, address, shops)
    - Возвращает данные пользователя
```

## Важные моменты

### 1. Формат токена

- **Правильный формат:** `{id}|{token}` (например: `1503|Wc7ziBiSKWYCVAJ...`)
- **Неправильный формат:** `{hash}|{id}|{token}` (возникал из-за шифрования cookie)
- **Middleware FixSanctumToken** автоматически исправляет формат

### 2. Шифрование cookie

- Токен `pixer-auth-token` **не шифруется** (добавлен в исключения `EncryptCookies`)
- Причина: токен уже защищен через Bearer token в заголовке Authorization
- Шифрование cookie вызывало проблемы с форматом токена

### 3. Конфигурация auth guard

- Guard `api` использует драйвер `sanctum`
- Конфигурация берется из `packages/marvel/config/auth.php` (переопределяет основную)
- Middleware `auth:sanctum` правильно обрабатывает Bearer токены

### 4. Отмена запросов без токена

- HTTP Client отменяет запросы к защищенным эндпоинтам, если токена нет
- Это предотвращает лишние 401 ошибки при загрузке страницы
- React Query игнорирует отмененные запросы

## Файлы, которые были изменены

### Бэкенд

1. `pixer-api/app/Http/Middleware/EncryptCookies.php`
   - Добавлен `pixer-auth-token` в исключения

2. `pixer-api/app/Http/Middleware/FixSanctumToken.php` (создан)
   - Middleware для исправления формата токена

3. `pixer-api/app/Http/Kernel.php`
   - Добавлен `FixSanctumToken` в группу middleware `api`

4. `pixer-api/packages/marvel/config/auth.php`
   - Guard `api` использует драйвер `sanctum`
   - Добавлен `hash => false`

5. `pixer-api/packages/marvel/src/Rest/Routes.php`
   - Изменен middleware с `auth:api` на `auth:sanctum` для роута `/me`
   - Добавлены тестовые роуты `/test-auth` и `/test-auth-debug`

### Фронтенд

Изменения на фронтенде были минимальными, так как проблема была на бэкенде:

1. `shop/src/data/client/http-client.ts`
   - Улучшена логика отмены запросов без токена
   - Добавлены публичные эндпоинты в список исключений

2. `shop/src/data/user.ts`
   - Улучшена обработка ошибок в `useMe()`
   - Добавлена проверка отмененных запросов

## Тестирование

### Скрипты для проверки

1. `pixer-api/check-token.php` - проверка токена из cookie
2. `pixer-api/test-auth.php` - тест авторизации через HTTP
3. `pixer-api/test-auth-direct.php` - тест авторизации без HTTP

### Роуты для диагностики

1. `GET /api/test-auth-debug` - диагностика токена (без middleware)
2. `GET /api/test-auth` - тест с `auth:sanctum` middleware

## Результат

✅ Авторизация работает корректно
✅ Токен правильно передается и распознается
✅ Запросы к `/me` возвращают данные пользователя
✅ Нет лишних 401 ошибок при загрузке страницы
✅ Поддерживаются все методы авторизации: email/password, OTP, PIN-код

## Рекомендации

1. **Оставить middleware FixSanctumToken** - он исправляет формат токена для обратной совместимости
2. **Не удалять исключение pixer-auth-token** - токен не должен шифроваться
3. **Использовать auth:sanctum** вместо `auth:api` для новых роутов
4. **Проверять формат токена** при отладке проблем с авторизацией

