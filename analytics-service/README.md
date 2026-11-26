# Analytics service

Микросервис продуктовой аналитики на Go 1.25+: потребляет доменные события из RabbitMQ (`analytics.events`), записывает их батчами в ClickHouse (`ReplacingMergeTree`) и отдаёт агрегированные отчёты администраторам и аналитикам по JWT RS256.

## Конфигурация

Сервис валидирует обязательные настройки при старте (`config.Validate`) и не содержит зашитых учётных данных по умолчанию:

- `ANALYTICS_HTTP_PORT` (приоритетная) / `HTTP_PORT` — HTTP-порт сервера (по умолчанию `8080`).
- `CLICKHOUSE_ADDR`, `CLICKHOUSE_DB`, `CLICKHOUSE_USER`, `CLICKHOUSE_PASSWORD` — подключение к ClickHouse (`CLICKHOUSE_PASSWORD` обязателен в runtime).
- `RABBITMQ_URL`, `RABBITMQ_EXCHANGE` — подключение к RabbitMQ и topic-exchange событий (`RABBITMQ_URL` обязателен в runtime).
- `JWT_JWKS_URL` или `JWT_JWKS_JSON`, `JWT_ISSUER`, `JWT_AUDIENCE` — параметры проверки RS256 access-токенов (доступ к `/analytics/*` разрешён ролям `admin` и `analyst`).

## HTTP API

- `GET /health` — liveness-проверка без авторизации.
- `GET /ready` — readiness-проверка соединения с RabbitMQ и `Ping` ClickHouse.
- `GET /analytics/users?from=YYYY-MM-DD&to=YYYY-MM-DD&granularity=day|week|month` — регистрации пользователей.
- `GET /analytics/orders?from=YYYY-MM-DD&to=YYYY-MM-DD` — воронка, когортная конверсия, чистая выручка и средний чек.
- `GET /analytics/search?from=YYYY-MM-DD&to=YYYY-MM-DD` — популярные поисковые запросы и запросы с нулевой выдачей.

Ошибки авторизации, валидации и сбоев отчётов возвращаются в формате `application/problem+json` (RFC 9457).

## Выручка

Чистая выручка за окно — оплаты минус возвраты:

`sum(total_amount)` по событиям `order.paid` − `sum(refunded_amount)` по `order.cancelled` и `order.refunded`.

`order-service` (`OutboxRelay::publishAnalytics`) публикует `order.created`, `order.paid`, `order.cancelled` (с полем `refunded_amount`) и `order.refunded` (при фактическом возврате средств). Деньги хранятся как `Decimal`: JSON-число или строка, не `float64`.

Ожидается один сигнал на возврат. Если прислать оба, сумма вычтется дважды.

Отмена с необязательным `refunded_amount` (нет поля — возврат 0):

```json
{
  "event": "order.cancelled",
  "event_id": "6f1c1c1c-1c1c-4c1c-8c1c-1c1c1c1c1c1c",
  "order_id": "22222222-2222-4222-8222-222222222222",
  "user_id": 4,
  "store_id": 1,
  "total_amount": 21,
  "refunded_amount": 21,
  "occurred_at": "2026-11-25T12:00:00+00:00"
}
```

Либо отдельное событие `order.refunded`:

```json
{
  "event": "order.refunded",
  "event_id": "7a2d2d2d-2d2d-4d2d-8d2d-2d2d2d2d2d2d",
  "order_id": "22222222-2222-4222-8222-222222222222",
  "user_id": 4,
  "store_id": 1,
  "refunded_amount": "21.00",
  "occurred_at": "2026-11-25T12:00:00+00:00"
}
```

Средний чек — чистая выручка, делённая на число оплаченных заказов в окне.

## Конверсия

Когорта — заказы с `order.created` в выбранном окне.

`created_to_paid` = (заказы когорты, у которых в этом же окне есть `order.paid`) / (размер когорты).

`created_to_cancelled` считается так же по `order.cancelled`.

Если созданных заказов нет, обе доли равны 0. Доля не превышает 1 (100%). Оплата заказа, созданного вне окна, в конверсию не входит.

Воронка (`created`, `paid`, `cancelled`) — это объёмы событий в окне, а не когорты. Поле `completed` не отдаётся: оно всегда совпадало с `paid`.

## Проверки и тесты

```bash
cd analytics-service/backend

# Форматирование и зависимости
gofmt -w .
go mod tidy -diff

# Статический анализ и unit/контрактные тесты (включая golden fixtures из contracts/)
go vet ./...
go test -race ./...

# End-to-end интеграционный тест (RabbitMQ consumer -> ClickHouse, тег integration)
CLICKHOUSE_ADDR=127.0.0.1:9000 RABBITMQ_URL=amqp://guest:guest@127.0.0.1:5672/ \
  go test -race -tags=integration ./tests/integration/...
```
