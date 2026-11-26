# Notification Service

Микросервис доставки email-уведомлений и ведения журнала отправки для e-commerce платформы.

## Назначение и архитектура

`notification-service` принимает асинхронные доменные события из Apache Kafka, валидирует входные контракты через `readonly`-DTO, отправляет локализованные HTML-письма, сохраняет историю доставки в MongoDB в виде нативных BSON-документов и предоставляет защищённый REST API для просмотра журнала и повторной отправки (`replay`) неуспешных уведомлений.

### Основные компоненты

- **Kafka-консьюмеры** (`app/Messaging/Consumers/`):
  - `AuthEventsConsumer` (`auth.events`) — обрабатывает `user.registered`, `auth.password_reset`, `auth.two_factor_code`.
  - `OrderEventsConsumer` (`order.events`) — обрабатывает `order.paid`, `order.confirmed`, `order.cancelled` (прочие события топика `order.events` безопасно подтверждаются без отправки писем).
  - Ретраи временных сбоев (`Sleep::usleep`), ручной коммит оффсетов (`withManualCommit()`) и маршрутизация битых сообщений (`NonRetryableException`) в Dead Letter Queue выполняются базовой командой `Ecommerce\PlatformKit\Laravel\ConsumeKafkaCommand`.
  - Идемпотентность обеспечивается дедупликацией по `event_id` и уникальным частичным индексом MongoDB `notification_logs_event_id_unique`.
- **Безопасность секретов**:
  - Коды `2FA` (`code`) и ссылки сброса пароля (`reset_url`) вырезаются через `SensitivePayload::redact()` **до** сохранения документа в MongoDB.
  - Повторная отправка (`replay`) уведомлений `auth.two_factor_code` и `auth.password_reset` запрещена и возвращает `422 application/problem+json` (`code: notification_secret_redacted`).
  - Публичное WebSocket-вещание полностью отключено.
- **Хранилище**: MongoDB (`laravel-mongodb` v5, коллекция `notification_logs`, TTL-индекс 90 дней и ежедневная очистка по расписанию).

## REST API

Все маршруты защищены JWT (`auth:api`, проверка подписи по JWKS через `ecommerce/platform-kit`) и RBAC-разрешениями:

| Метод | Маршрут | Разрешение | Роли | Описание |
|---|---|---|---|---|
| `GET` | `/api/v1/notifications` | `notifications.read` | `admin`, `analyst` | Пагинированный список уведомлений (`status`, `channel`, `recipient`, `date_from`, `date_to`, `per_page` 1..100) |
| `GET` | `/api/v1/notifications/{id}` | `notifications.read` | `admin`, `analyst` | Детальная карточка записи журнала (без `payload`) |
| `POST` | `/api/v1/notifications/{id}/replay` | `notifications.replay` | `admin` | Повторная отправка `failed`-уведомления с записью аудита (`replayed_by`, `replayed_at`, `notification.replayed`) |
| `GET` | `/up` | — | — | Проверка работоспособности сервиса |

OpenAPI-спецификация описана в `app/OpenApi/NotificationOpenApi.php`. Веб-интерфейс Swagger UI активен только при `APP_ENV=local`.

## Консольные команды

```bash
# Запуск консьюмеров Kafka
php artisan notification:consume-auth-events
php artisan notification:consume-order-events

# Принудительная зачистка секретов ($unset payload.data.code и payload.data.reset_url)
php artisan notification:redact-secrets
# Алиас:
php artisan notifications:purge-secrets

# Миграция устаревших JSON-строк payload в нативные BSON-объекты
php artisan notifications:convert-payloads

# Очистка устаревших записей журнала (по расписанию запускается ежедневно)
php artisan notification:prune-logs --days=90
```

## Проверки качества и тестирование

Требования: PHP `~8.5.0`, расширения `intl`, `mongodb`, `rdkafka` (в контейнере `ecommerce-php-base:8.5`).

```bash
# Проверка composer.json и composer.lock
composer validate --strict

# Форматирование кода (Laravel Pint)
composer lint
composer lint:fix

# Автоматический рефакторинг (Rector)
composer refactor
composer refactor:fix

# Статический анализ (PHPStan Level 8 + Larastan)
composer analyse

# Запуск тестов Pest (Unit, Feature, Arch)
composer test

# Полный конвейер проверок (@lint, @refactor, @analyse, @test)
composer check
```
