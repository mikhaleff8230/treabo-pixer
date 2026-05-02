# Документация: Логика создания товара и взимания платы за размещение

## Обзор процесса

Данный документ описывает полный процесс создания и публикации товара в системе, включая логику взимания платы за размещение товара.

---

## Этапы процесса

### 1. Создание товара (Черновик)

**Файлы:**
- `admin/src/components/product/editor/ProductEditor.tsx` - основной компонент редактора
- `admin/src/components/product/editor/hooks/useAutosave.ts` - автосохранение
- `pixer-api/packages/marvel/src/Http/Controllers/ProductController.php` - контроллер создания
- `pixer-api/packages/marvel/src/Database/Repositories/ProductRepository.php` - репозиторий (метод `storeProduct`)

**Процесс:**
1. Пользователь заполняет форму товара в edit-wizard
2. При изменении полей срабатывает автосохранение (через 2 секунды после последнего изменения)
3. Автосохранение **всегда сохраняет как `draft`**, независимо от выбранного статуса в форме
4. При нажатии кнопки "Сохранить" товар сохраняется как `draft`
5. **Оплата НЕ происходит** при создании черновика

**Ключевые моменты:**
- Автосохранение: `useAutosave.ts` - всегда устанавливает `status: 'draft'`
- Создание товара: `ProductRepository::storeProduct` - оплата отключена, только логирование

---

### 2. Публикация товара (Опубликовано)

**Файлы:**
- `admin/src/components/product/editor/ProductEditor.tsx` - обработка кнопки "Опубликовать"
- `admin/src/components/product/payment-confirmation-modal.tsx` - модальное окно подтверждения
- `admin/src/components/ui/modal/managed-modal.tsx` - управление модальными окнами
- `pixer-api/packages/marvel/src/Http/Controllers/ProductController.php` - контроллер обновления
- `pixer-api/packages/marvel/src/Database/Repositories/ProductRepository.php` - репозиторий (метод `updateProduct`)
- `pixer-api/packages/marvel/src/Services/PaymentService.php` - сервис оплаты

**Процесс:**
1. Пользователь нажимает кнопку "Опубликовать" на последнем шаге
2. Показывается модальное окно подтверждения оплаты:
   - Иконка кошелька
   - Текст: "Будет списано 49.00 ₽"
   - Кнопки: "Сбросить" и "Опубликовать"
3. При нажатии "Опубликовать":
   - Товар сохраняется со статусом `publish`
   - На бэкенде проверяется изменение статуса с любого на `PUBLISH`
   - Если статус изменился на `PUBLISH` → вызывается `PaymentService::payForProduct`
4. Проверка оплаты:
   - Проверяется, является ли пользователь супер-админом (если да - оплата пропускается)
   - Получается баланс продавца (`SellerBalance`)
   - Проверяется достаточность средств (49.00 ₽)
   - Если средств достаточно → списываются средства, товар публикуется
   - Если средств недостаточно → товар переводится в `draft`, возвращается ошибка

**Ключевые моменты:**
- Модальное окно показывается только при явном нажатии кнопки "Опубликовать"
- Оплата происходит только при изменении статуса с любого на `PUBLISH`
- Супер-админ не платит за размещение товаров

---

## Детальная логика по файлам

### Frontend (Admin)

#### 1. `ProductEditor.tsx`
**Роль:** Основной компонент редактора товара

**Методы:**
- `handleSave(data, publish)` - обработка сохранения
  - Если `publish = false` → сохраняет как `draft`
  - Если `publish = true` → показывает модальное окно

**Компоненты:**
- `EditorActionsWrapper` - обертка для кнопок действий
  - При `publish = true` → открывает модальное окно `PAYMENT_CONFIRMATION`
  - При `publish = false` → сохраняет сразу как `draft`

#### 2. `useAutosave.ts`
**Роль:** Автосохранение товара при изменении полей

**Логика:**
- Срабатывает через 2 секунды после последнего изменения
- **Всегда сохраняет как `draft`**, даже если в форме выбран статус `publish`
- Использует `useAutoSaveProductMutation` (тихое сохранение без уведомлений)

#### 3. `payment-confirmation-modal.tsx`
**Роль:** Модальное окно подтверждения оплаты

**Параметры:**
- `amount` - сумма списания (49.00 ₽)
- `onCancel` - отмена публикации
- `onConfirm` - подтверждение публикации

**Отображение:**
- Иконка кошелька (`WalletPointsIcon`)
- Текст: "Будет списано 49.00 ₽"
- Кнопки: "Сбросить" (желтая) и "Опубликовать" (зеленая)

---

### Backend (API)

#### 1. `ProductController.php`

**Метод `store()` (создание товара):**
```php
public function store(ProductCreateRequest $request)
{
    return $this->ProductStore($request);
}
```

**Метод `ProductStore()`:**
- Проверяет права доступа через `hasPermission($user, $shop_id)`
- Вызывает `repository->storeProduct($request, $setting)`
- **Оплата НЕ происходит** при создании

**Метод `update()` (обновление товара):**
```php
public function update(ProductUpdateRequest $request, $id)
{
    $request->id = $id;
    return $this->updateProduct($request);
}
```

**Метод `updateProduct()`:**
- Получает `shop_id` из товара (если не передан в запросе)
- Проверяет права доступа через `hasPermission($user, $shop_id)`
- Вызывает `repository->updateProduct($request, $id, $setting)`
- **Оплата происходит только при изменении статуса на PUBLISH**

#### 2. `ProductRepository.php`

**Метод `storeProduct()`:**
- Создает товар в базе данных
- Устанавливает статус из запроса (обычно `draft`)
- **Оплата отключена** - только логирование
- Возвращает созданный товар

**Метод `updateProduct()`:**
- Обновляет товар в базе данных
- Сохраняет старый статус для проверки изменения
- **Проверяет изменение статуса на PUBLISH:**
  ```php
  if ($newStatus === ProductStatus::PUBLISH && $oldStatus !== ProductStatus::PUBLISH) {
      // Вызывается PaymentService::payForProduct
  }
  ```
- Если статус изменился на `PUBLISH` → вызывается оплата
- Если оплата не удалась → товар переводится в `draft`, возвращается ошибка

**Метод `hasPermission()` (наследуется из BaseRepository):**
- Проверяет права доступа пользователя к магазину
- Супер-админ имеет доступ ко всем магазинам
- Владелец магазина имеет доступ к своему магазину
- Персонал имеет доступ к привязанному магазину

#### 3. `PaymentService.php`

**Метод `payForProduct(Product $product)`:**
- Проверяет, является ли текущий пользователь супер-админом
  - Если да → пропускает оплату, устанавливает `paid_until` без списания
- Получает магазин товара и `owner_id` (seller_id)
- Получает или создает баланс продавца (`SellerBalance::getOrCreate`)
- Проверяет достаточность средств (49.00 ₽)
- Если средств достаточно:
  - Списывает средства (`balance->withdraw`)
  - Устанавливает `paid_until` (180 дней)
  - Сохраняет данные об оплате в товаре
- Если средств недостаточно:
  - Переводит товар в `draft`
  - Возвращает ошибку

**Константы:**
- `PRODUCT_PLACEMENT_COST = 49.00` - стоимость размещения товара
- `PAYMENT_PERIOD_DAYS = 180` - период оплаты (дней)

---

## Схема потока данных

### Создание черновика:
```
User → ProductEditor → handleSave(publish=false) 
  → API: POST /products 
  → ProductController::store 
  → ProductRepository::storeProduct 
  → Товар создан (status=draft, оплата НЕ происходит)
```

### Автосохранение:
```
User изменяет поля → useAutosave (debounce 2 сек) 
  → useAutoSaveProductMutation 
  → API: PUT /products/{id} (status=draft всегда)
  → ProductController::update 
  → ProductRepository::updateProduct 
  → Товар обновлен (статус не меняется на PUBLISH, оплата НЕ происходит)
```

### Публикация товара:
```
User → Кнопка "Опубликовать" 
  → Модальное окно подтверждения 
  → User подтверждает 
  → handleSave(publish=true) 
  → API: PUT /products/{id} (status=publish)
  → ProductController::update 
  → ProductRepository::updateProduct 
  → Проверка: статус изменился на PUBLISH?
    → Да → PaymentService::payForProduct
      → Проверка баланса
      → Если достаточно → списание, публикация
      → Если недостаточно → товар в draft, ошибка
```

---

## Проверка прав доступа

### Метод `hasPermission($user, $shop_id)`:

1. **Супер-админ:**
   - Имеет доступ ко всем магазинам
   - Не платит за размещение товаров

2. **Владелец магазина (STORE_OWNER):**
   - Имеет доступ только к своему магазину (`shop->owner_id === user->id`)
   - Платит за размещение товаров

3. **Персонал (STAFF):**
   - Имеет доступ к привязанному магазину (`shop->staffs->contains($user)`)
   - Платит за размещение товаров (списывается с баланса владельца)

4. **Проверка магазина:**
   - Магазин должен быть активен (`shop->is_active === true`)
   - Если магазин неактивен → ошибка `SHOP_NOT_APPROVED`

---

## Обработка ошибок

### Ошибка авторизации (NOT_AUTHORIZED):
- Возникает при отсутствии прав доступа к магазину
- Обрабатывается в `http-client.ts`:
  - Если 403 с токеном → показывается уведомление (не разлогинивает)
  - Если 401 без токена → разлогинивает пользователя

### Ошибка недостатка средств:
- Возникает при попытке публикации товара с недостаточным балансом
- Товар автоматически переводится в `draft`
- Возвращается ошибка с сообщением о недостатке средств
- Показывается уведомление на фронтенде

---

## Важные моменты

1. **Автосохранение всегда сохраняет как draft** - это предотвращает случайную публикацию
2. **Оплата происходит только при явной публикации** - через кнопку "Опубликовать" с подтверждением
3. **Супер-админ не платит** - оплата пропускается автоматически
4. **Проверка прав доступа** - происходит на уровне контроллера перед обновлением товара
5. **shop_id определяется из товара** - при обновлении используется shop_id из БД, а не из запроса

---

## Файлы, участвующие в процессе

### Frontend:
- `admin/src/components/product/editor/ProductEditor.tsx`
- `admin/src/components/product/editor/hooks/useAutosave.ts`
- `admin/src/components/product/editor/EditorActions.tsx`
- `admin/src/components/product/payment-confirmation-modal.tsx`
- `admin/src/components/ui/modal/managed-modal.tsx`
- `admin/src/components/ui/modal/modal.context.tsx`
- `admin/src/data/product.ts`
- `admin/src/data/client/http-client.ts`

### Backend:
- `pixer-api/packages/marvel/src/Http/Controllers/ProductController.php`
- `pixer-api/packages/marvel/src/Http/Controllers/ProductWizardController.php`
- `pixer-api/packages/marvel/src/Database/Repositories/ProductRepository.php`
- `pixer-api/packages/marvel/src/Database/Repositories/BaseRepository.php`
- `pixer-api/packages/marvel/src/Services/PaymentService.php`
- `pixer-api/app/Models/SellerBalance.php`
- `pixer-api/packages/marvel/src/Http/Requests/ProductCreateRequest.php`
- `pixer-api/packages/marvel/src/Http/Requests/ProductUpdateRequest.php`

### Локализация:
- `admin/public/locales/ru/common.json`
- `admin/public/locales/en/common.json`
- (и другие языки)

---

## Логика оплаты (пошагово)

### Шаг 1: Пользователь нажимает "Опубликовать"
- Файл: `ProductEditor.tsx` → `EditorActionsWrapper` → `onSave(true)`
- Действие: Открывается модальное окно подтверждения

### Шаг 2: Пользователь подтверждает в модальном окне
- Файл: `payment-confirmation-modal.tsx` → `onConfirm()`
- Действие: Закрывается модальное окно, вызывается `performSave(true)`

### Шаг 3: Отправка запроса на сервер
- Файл: `ProductEditor.tsx` → `handleSave(data, publish=true)`
- Действие: Отправляется PUT запрос с `status: 'publish'`

### Шаг 4: Проверка прав доступа
- Файл: `ProductController.php` → `updateProduct()`
- Действие: Проверяется `hasPermission($user, $shop_id)`
- Если нет прав → ошибка `NOT_AUTHORIZED`

### Шаг 5: Обновление товара
- Файл: `ProductRepository.php` → `updateProduct()`
- Действие: Товар обновляется в БД, сохраняется старый статус

### Шаг 6: Проверка изменения статуса
- Файл: `ProductRepository.php` → `updateProduct()`
- Условие: `if ($newStatus === PUBLISH && $oldStatus !== PUBLISH)`
- Если условие выполнено → вызывается оплата

### Шаг 7: Оплата товара
- Файл: `PaymentService.php` → `payForProduct()`
- Проверки:
  1. Является ли пользователь супер-админом? → Если да, пропустить оплату
  2. Получить баланс продавца
  3. Достаточно ли средств? → Если нет, товар в draft, ошибка
  4. Списать средства
  5. Установить `paid_until` (180 дней)

### Шаг 8: Результат
- Если успешно → товар опубликован, средства списаны
- Если ошибка → товар в draft, показывается уведомление об ошибке

---

## Решение проблемы NOT_AUTHORIZED

**Проблема:** Ошибка `PIXER_ERROR.NOT_AUTHORIZED` возникает при обновлении товара в edit-wizard, если `shop_id` не передается в запросе или передается неправильный.

**Причина:**
- При обновлении товара через edit-wizard `shop_id` может не передаваться в запросе
- В `ProductController::updateProduct` проверка прав доступа происходила с `$request->shop_id`, который мог быть `null`
- Метод `hasPermission($user, null)` возвращал `false`, что приводило к ошибке `NOT_AUTHORIZED`

**Решение:** 
В `ProductController::updateProduct` получать `shop_id` из самого товара перед проверкой прав:

```php
// Получаем товар из БД
$product = $this->repository->findOrFail($id);
// Используем shop_id из товара, если он не передан в запросе
$shopId = $request->shop_id ?? $product->shop_id;

// Проверяем, что shop_id существует
if (!$shopId) {
    throw new AuthorizationException(NOT_AUTHORIZED);
}

// Проверяем права доступа с правильным shop_id
if ($this->repository->hasPermission($request->user(), $shopId)) {
    return $this->repository->updateProduct($request, $id, $setting);
} else {
    throw new AuthorizationException(NOT_AUTHORIZED);
}
```

**Результат:**
- Проверка прав доступа всегда происходит с правильным `shop_id`
- Ошибка `NOT_AUTHORIZED` не возникает при обновлении товара селлером
- Если `shop_id` отсутствует и в товаре, и в запросе → возвращается ошибка (корректное поведение)

---

## Защита от повторной оплаты

**Проблема:** При повторной публикации уже опубликованного товара не должно происходить повторное списание средств.

**Решение:**
В `ProductRepository::updateProduct` проверяется изменение статуса:

```php
// Сохраняем старый статус
$oldStatus = $product->status;
$newStatus = $data['status'] ?? $oldStatus;

// Обновляем товар
$product->update($data);

// Оплата происходит ТОЛЬКО при изменении статуса с любого на PUBLISH
if ($newStatus === ProductStatus::PUBLISH && $oldStatus !== ProductStatus::PUBLISH) {
    // Вызывается оплата
    $paymentService->payForProduct($product);
}
```

**Результат:**
- Если товар уже был опубликован (`oldStatus === PUBLISH`), оплата не происходит
- Оплата происходит только при первой публикации товара
- При повторной публикации товар просто обновляется без списания средств

