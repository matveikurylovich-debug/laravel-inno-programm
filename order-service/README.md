# Order Service

Сервис управления заказами, платежами Stripe и оркестрации распределённой саги оформления заказа.

## Назначение и зона ответственности

`order-service` отвечает за:
- приём и идемпотентное создание заказов по локальным снапшотам товаров (`product_snapshots`);
- управление жизненным циклом заказа и координацию саги со складом (`stock.commands` / `inventory.events`);
- создание и отмену `Stripe PaymentIntent`, приём подписанных Stripe Webhooks и выполнение возвратов (`Refund`);
- гарантированную доставку доменных событий в Apache Kafka и аналитический брокер RabbitMQ через паттерн **Transactional Outbox**.

Заказы являются финансовыми записями: физическое (`DELETE`) и мягкое (`SoftDeletes`) удаление заказов запрещено — при отмене заказ переходит в терминальный статус `cancelled` с сохранением истории и компенсационными событиями.

---

## Состояния заказа и переходы саги

### Статусы заказа (`OrderStatus`)

- `created` — заказ создан в БД вместе с событием `order.created` в `outbox_messages`; ожидается резервирование остатков на складе.
- `stock_reserved` — склад подтвердил резерв (`stock.reserved`); сервис инициализирует `Stripe PaymentIntent`.
- `payment_pending` — `PaymentIntent` создан, установлен дедлайн оплаты (`payment_expires_at`); ожидается оплата покупателем.
- `paid` — оплата подтверждена Stripe (`payment_intent.succeeded`); публикуются `order.paid` и команда списания резерва `stock.commit_requested`.
- `confirmed` — склад подтвердил финальное списание (`stock.committed`); публикуется `order.confirmed`.
- `cancelled` — терминальный статус отмены (по запросу пользователя, при отказе резерва `stock_unavailable`, при ошибке списания `stock_commit_failed` или по истечении тайм-аута `payment_timeout` / `reserve_timeout`).

### Разрешённые переходы

| Из статуса | Допустимые целевые статусы |
|---|---|
| `created` | `stock_reserved`, `cancelled` |
| `stock_reserved` | `payment_pending`, `cancelled` |
| `payment_pending` | `paid`, `cancelled` |
| `paid` | `confirmed`, `cancelled` |
| `confirmed` | `cancelled` |
| `cancelled` | — (терминальный) |

### Статусы оплаты (`PaymentStatus`)

`pending` → `paid` / `succeeded` / `failed` → `refund_pending` → `refunded`.
Отказ карты (`payment_intent.payment_failed`) не отменяет заказ автоматически: заказ остаётся в `payment_pending` до успешной повторной попытки или истечения `payment_expires_at`.

---

## HTTP API

Все защищённые маршруты используют JWT-аутентификацию (`auth:api`, guard по умолчанию — `api`) и возвращают ошибки в формате RFC 9457 (`application/problem+json`).

| Метод | Путь | Описание |
|---|---|---|
| `GET` | `/api/orders` | Пагинированный список заказов (`customer` видит свои, `admin` и `analyst` — все) |
| `POST` | `/api/orders` | Создание заказа (обязателен заголовок `Idempotency-Key`) |
| `GET` | `/api/orders/{order}` | Детали заказа (`stripe_client_secret` доступен только владельцу; `email` скрыт от `analyst`) |
| `POST` | `/api/orders/{order}/cancel` | Отмена заказа с освобождением резерва и постановкой возврата в очередь при оплате |
| `POST` | `/webhooks/stripe` | Приём подписанных событий Stripe (`Stripe-Signature`, без сессий и CSRF) |
| `GET` | `/up` | Liveness-проверка приложения |
| `GET` | `/ready` | Readiness-проверка отставания `outbox_messages` (`503`, если лаг превышает `ORDER_OUTBOX_LAG_SECONDS`) |

Swagger UI доступен только в окружении `APP_ENV=local` (`/api/documentation`); спецификация OpenAPI вынесена в `app/OpenApi/`.

---

## Идемпотентность

`POST /api/orders` защищён middleware `EnsureIdempotentRequest` (`idempotency`):
- требует непустой заголовок `Idempotency-Key` (иначе `400 Bad Request`);
- захватывает распределённую блокировку `Cache::lock` на время выполнения запроса (`409 Conflict` при конкурирующем дубле);
- сохраняет успешный ответ на 24 часа вместе с SHA-256 отпечатком тела запроса:
  - повтор с тем же телом возвращает сохранённый ответ с заголовком `X-Idempotent-Replay: true`;
  - повтор того же ключа с изменённым телом отклоняется с `422 Unprocessable Entity`.

---

## Stripe Webhooks и возвраты

Конфигурация Stripe сосредоточена в `config/services.php` (`services.stripe`: `secret`, `publishable`, `webhook_secret`, `currency`), а `Stripe\StripeClient` регистрируется в контейнере в `AppServiceProvider`.

1. **Верификация и дедупликация**: `StripeWebhookController` проверяет заголовок `Stripe-Signature` через `STRIPE_WEBHOOK_SECRET` и записывает `event_id` в таблицу `stripe_events` (`insertOrIgnore`), гарантируя однократную обработку каждого вебхука.
2. **Обрабатываемые события**:
   - `payment_intent.succeeded` — переводит заказ в `paid` и запрашивает `stock.commit_requested` (а если заказ уже успел перейти в `cancelled`, инициирует автоматический возврат);
   - `payment_intent.payment_failed` — фиксирует `payment_status = failed` и публикует `order.payment_failed`;
   - `refund.created`, `refund.updated`, `refund.failed` — синхронизирует статус возврата и при `succeeded` однократно записывает событие `order.refunded` в outbox.
3. **Очередь возвратов**: при отмене оплаченного заказа задача `App\Jobs\RefundCancelledOrder` отправляется в очередь `payments` (соединение `database` внутри транзакции отмены) с идемпотентным ключом Stripe и `backoff` `[10, 30, 60, 300]`.

---

## Transactional Outbox, Kafka и DLQ

Все исходящие события записываются в таблицу `outbox_messages` в одной БД-транзакции с изменением заказа.

### Artisan-команды

- `php artisan order:relay-outbox [--batch=50] [--sleep=1] [--once]` — вычитывает `pending`-сообщения с сохранением порядка внутри `aggregate_id` (`FOR UPDATE SKIP LOCKED` на PostgreSQL), публикует доменные события в Kafka (`order.events` / `stock.commands`) и аналитические события в RabbitMQ (`analytics.events`) с экспоненциальным повтором при сбоях брокера.
- `php artisan order:retry-outbox --failed` — возвращает сообщения со статусом `dead` обратно в `pending`.
- `php artisan order:consume-catalog-events` — синхронизирует таблицу `product_snapshots` из топика `catalog.events` (DLQ: `catalog.events.dlq.order-service`).
- `php artisan order:consume-inventory-events` — обрабатывает ответы склада из `inventory.events` (DLQ: `inventory.events.dlq.order-service`).
- `php artisan order:replay-dlq {topic} [--replay]` — выводит сообщения из DLQ-топика и при флаге `--replay` перепубликует их в исходный топик.
- `php artisan order:expire-orders` — запускается по расписанию каждую минуту: отменяет заказы с просроченной оплатой (`payment_timeout`) и однократно перепубликует зависший резерв перед отменой по `reserve_timeout`.

В `ConsumerFactory` включён ручной коммит (`withManualCommit()`) и `withDlq()`: транзиентные ошибки повторяются `kafka.failed_message_retries` раз с паузой `kafka.failed_message_backoff_ms`, а `NonRetryableException` / `NonRetryableMessageException` немедленно пробрасываются в DLQ без повторов.

---

## Проверка качества и тесты

Тестовый набор написан на **Pest** и разделён в `phpunit.xml` на сьюты `Unit`, `Feature`, `Integration` (группа `pgsql`) и `Arch` (`tests/Arch/LayoutTest.php`).

```bash
# Строгая валидация composer.json и composer.lock
composer validate --strict

# Форматирование кода (Laravel Pint)
composer lint
composer lint:fix

# Автоматический рефакторинг (Rector)
composer refactor
composer refactor:fix

# Статический анализ (PHPStan / Larastan Level 8)
composer analyse

# Запуск тестов (Unit + Feature + Arch на SQLite in-memory)
composer test

# Полный конвейер проверок (lint + refactor + analyse + test)
composer check
```
