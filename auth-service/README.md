# auth-service

Headless HTTP API сервис аутентификации и управления пользователями платформы (Laravel 13, PHP 8.5).

## Зона ответственности

- Регистрация пользователей, нормализация email/телефона и выдача пары JWT (`RS256` access + `HS256` refresh)
- Двухфакторная аутентификация по одноразовому коду (`2FA`) с защитой от перебора
- Ротация refresh-токенов с grace-окном и отзывом всех сессий при повторном использовании
- Публикация JWKS (`/api/v1/internal/jwks`) и проверка access-токена для API Gateway (`/api/v1/gateway/verify`)
- Управление профилем, смена пароля, подтверждаемая смена email и мягкое удаление аккаунта (`SoftDeletes` с очисткой через 30 дней)
- RBAC на базе `spatie/laravel-permission` (`admin`, `analyst`, `customer`) с журналом аудита смены ролей (`role_audit_logs`) и защитой последнего администратора
- Асинхронная публикация доменных событий в Kafka (`auth.events`) и аналитики в RabbitMQ (`analytics.events`)

## Основные эндпоинты (`/api/v1/*`)

| Метод | Путь | Доступ | Назначение |
|---|---|---|---|
| `GET` | `/up` | public | Healthcheck сервиса |
| `GET` | `/api/v1/internal/jwks` | internal | Публичные ключи проверки access-токенов |
| `POST` | `/api/v1/register` | guest (`throttle:register`) | Регистрация нового покупателя |
| `POST` | `/api/v1/login` | guest (`throttle:login`) | Проверка пароля и отправка кода 2FA |
| `POST` | `/api/v1/two-factor/verify` | guest (`throttle:two-factor`) | Подтверждение 2FA и выпуск пары токенов |
| `POST` | `/api/v1/two-factor/resend` | guest (`throttle:two-factor`) | Повторная отправка кода 2FA |
| `POST` | `/api/v1/forgot-password` | guest (`throttle:password-reset`) | Запрос ссылки сброса пароля |
| `POST` | `/api/v1/reset-password` | guest (`throttle:password-reset`) | Сброс пароля по токену |
| `POST` | `/api/v1/refresh` | public | Ротация refresh-токена |
| `POST` | `/api/v1/logout` | optional `auth:api` | Отзыв текущих access/refresh токенов |
| `POST` | `/api/v1/logout-all` | `auth:api` | Отзыв всех сессий пользователя |
| `GET` | `/api/v1/me` | `auth:api` | Профиль текущего пользователя |
| `GET` | `/api/v1/gateway/verify` | `auth:api` | Проверка access-токена для шлюза |
| `PATCH` | `/api/v1/profile` | `auth:api` | Обновление имени и телефона |
| `POST` | `/api/v1/profile/email` | `auth:api` | Запрос смены email |
| `GET` | `/api/v1/profile/email/confirm` | `signed` | Подтверждение нового email |
| `GET` | `/api/v1/profile/email/cancel` | `signed` | Отмена смены email |
| `PUT` | `/api/v1/profile/password` | `auth:api` | Смена пароля |
| `DELETE` | `/api/v1/profile` | `auth:api` | Мягкое удаление аккаунта |
| `GET` | `/api/v1/admin/users` | `admin`, `analyst` | Постраничный список пользователей |
| `GET` | `/api/v1/admin/roles` | `admin`, `analyst` | Список доступных ролей |
| `PATCH` | `/api/v1/admin/users/{user}/role` | `admin` | Изменение роли пользователя с записью в аудит |
| `GET` | `/api/v1/catalog-admin/access` | `admin` | Проверка прав администратора каталога |

## Ключевые переменные окружения

- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — подключение к PostgreSQL
- `REDIS_HOST`, `REDIS_PORT`, `REDIS_DB`, `REDIS_CACHE_DB` — хранение refresh-токенов, блэклиста, `not_before`, кэша 2FA и rate limiter
- `JWT_ISSUER`, `JWT_AUDIENCE`, `JWT_ACCESS_PRIVATE_KEY_PATH`, `JWT_ACCESS_PUBLIC_KEY_PATH`, `JWT_ACCESS_KID`, `JWT_REFRESH_SECRET`, `JWT_ACCESS_TTL`, `JWT_REFRESH_TTL` — настройки подписи и проверки JWT
- `KAFKA_BROKERS`, `KAFKA_TOPIC_AUTH_EVENTS` — публикация событий `auth.events`
- `ANALYTICS_RABBITMQ_URL`, `ANALYTICS_RABBITMQ_EXCHANGE` — отправка событий аналитики
- `FRONTEND_URL` — разрешённый `Origin` для браузерных cookie-сессий и базовый адрес ссылок сброса пароля
- `L5_SWAGGER_GENERATE_ALWAYS=false` — генерация OpenAPI только по команде; Swagger UI активен только при `APP_ENV=local`

## Полезные команды

```bash
# Создание или обновление администратора (идемпотентно)
php artisan auth:create-admin admin@example.test

# Принудительная очистка активных токенов сброса пароля
php artisan auth:invalidate-password-resets

# Очистка просроченных сбросов и мягко удалённых аккаунтов старше 30 дней (также в планировщике)
php artisan auth:clear-resets
php artisan model:prune

# Генерация OpenAPI-спецификации
php artisan l5-swagger:generate
```

## Контроль качества

Полный прогон проверок стиля (Pint), модернизации (Rector), статического анализа (PHPStan Level 8) и тестов (Pest: `Unit`, `Feature`, `Arch`):

```bash
composer validate --strict
composer check
```
