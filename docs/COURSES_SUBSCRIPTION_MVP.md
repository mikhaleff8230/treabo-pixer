# Курсы с подпиской (MVP) — реализация

## Модель данных

| Таблица | Назначение |
|---------|------------|
| `courses` | Курс: `title`, `description`, `required_product_id` (FK на `products`, nullable = бесплатный курс). |
| `lessons` | Урок: `course_id`, `title`, `content_type` (`video` \| `text` \| `file`), `content_url`, `content_body` (для текста), `position`, `drip_days`. |
| `lesson_progress` | Прогресс: `user_id`, `lesson_id`, `completed_at`, `progress_percent`, `last_watched_at`. |
| `subscriptions` | Дополнены полями: `starts_at`, `status` (`active` \| `expired` \| `cancelled`). |
| `products` | Дополнены: `billing_access_type` (`subscription` \| `one_time` \| `lifetime`, опционально), `duration_days` (приоритет над `subscription_days` в сервисе подписки). |

Контент видео/файлов — только URL в БД (S3/R2 + signed URLs — вне кода MVP).

## Сервисы (`app/Services/Courses/`)

- **`CourseSubscriptionService`** — `createOrExtendForUser()`, `subscriptionPeriodDays()`, `isActive()`. Используется хендлером цифрового товара `subscription` и задаёт `starts_at` / `expires_at` / `status`.
- **`CourseAccessService`** — `canAccessCourse()`, `canAccessLesson()`, `getActiveSubscription()`, `lessonAvailableAt()` (drip: `starts_at + drip_days`).
- **`CourseProgressService`** — `markLessonComplete()`, `getCourseProgress()`.

## API

| Метод | Путь | Описание |
|--------|------|----------|
| GET | `/courses` | Список курсов (+ `lessons_count`). |
| GET | `/courses/{id}` | Курс + уроки: `locked`, `completed`, `available_at` (Y-m-d или `null` без подписки). Bearer опционален. |
| GET | `/courses/{id}/progress` | `{ completed, total, percent }`. Auth + доступ к курсу. |
| GET | `/lessons/{id}` | Контент урока (URL/тело). Auth + доступ + drip. |
| POST | `/lessons/{id}/complete` | Отметить урок завершённым. |
| GET/POST | `/products/{id}/access` | Как раньше; подписка создаётся/продлевается через `CourseSubscriptionService`. |

## Правила доступа

1. **Курс** с `required_product_id`: доступ есть при активной записи в `subscriptions` (та же пара `user_id` + `product_id`, `status = active`, `expires_at > now()`).
2. **Бесплатный курс** (`required_product_id` null): курс доступен всем; уроки без drip-блокировки по подписке (сразу «открыты» в списке).
3. **Урок (drip)**: при платном курсе дата открытия: `subscription.starts_at + drip_days` (календарные дни через Carbon `addDays`).

## Интеграция с цифровыми товарами

`SubscriptionProductHandler` вызывает `CourseSubscriptionService::createOrExtendForUser()`:

- если подписка уже активна — возвращается текущий `expires_at`;
- иначе обновляется последняя запись или создаётся новая, выставляются `starts_at` и новый период.

**Важно:** выдача доступа к продукту по-прежнему завязана на `DigitalAccessGrantService` (покупка и т.д.). Повторная «продление» при истёкшей подписке возможна только если grant снова разрешён (например, новая покупка).

## Что доделать

- [ ] CRUD курсов/уроков в админке и привязка `required_product_id`.
- [ ] Валидация `content_type` и санитизация `content_body`.
- [ ] Подписанные URL для `content_url` (middleware или отдельный endpoint).
- [ ] Фоновое проставление `status = expired` по `expires_at`.
- [ ] Тесты: drip, прогресс, 403 без подписки.
- [ ] При необходимости развести «подписку на курс» и старые `digital_product_type = subscription` по `billing_access_type`.
