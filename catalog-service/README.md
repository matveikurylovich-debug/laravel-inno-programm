# Catalog Service (`ecommerce/catalog-service`)

Сервис каталога и складских остатков платформы электронной коммерции. Является источником истины по магазинам, точкам самовывоза, дереву категорий, карточкам товаров, медиафайлам в S3/MinIO, физическим остаткам и резервам под заказы.

Полнотекстовый и фасетный поиск вынесен в отдельный `search-service`, который подписывается на события каталога из Kafka.

---

## Доменные модели

- `Store` — магазин (`name`, `slug`, `city`, `address`, `phone`, `working_hours`, `is_active`).
- `StoreLocation` — точка самовывоза / склад магазина (`store_id`, `name`, `city`, `address`, `phone`, `working_hours`, `is_active`). Нельзя удалить при наличии остатков или резервов (`409`).
- `Category` — иерархия категорий внутри магазина (`store_id`, `parent_id`, `name`, `slug`, `position`, Adjacency List с защитой от циклов и проверкой принадлежности к одному магазину).
- `Product` — карточка товара (`store_id`, `category_id`, `sku`, `name`, `slug`, `description`, `price`, `is_active`, скалярные `attributes` в JSONB).
- `ProductImage` — изображения товара в S3/MinIO (`product_id`, `path`, `url`, `is_primary`, `position`).
- `Stock` — остаток товара в конкретной точке (`store_id`, `location_id`, `product_id`, `quantity`, `reserved_quantity`; инвариант БД `0 <= reserved_quantity <= quantity`).
- `StockReservation` — идемпотентная бронь товара под заказ (`order_id`, `store_id`, `location_id`, `product_id`, `quantity`, `status`: `reserved` | `confirmed` | `released`).
- `CatalogOutbox` — таблица Transactional Outbox (`catalog_outbox`) для атомарной записи доменных событий в рамках транзакции БД.
- `AdminUser` — локальная учётная запись администратора панели Filament (`admin-{userId}@catalog.internal`).

---

## HTTP API (`/api/v1`)

### Публичные маршруты (витрина)

- `GET /up` — проверка живучести сервиса.
- `GET /api/v1/stores` — список активных магазинов.
- `GET /api/v1/stores/{storeId}/categories/tree` — дерево категорий магазина (кэшируется в Redis).
- `GET /api/v1/stores/{storeId}/products` — список активных товаров магазина с фильтром `category_id` (включая подкатегории) и пагинацией (`page >= 1`, `1 <= per_page <= 100`, кэшируется с атомарным версионированием ключа магазина).
- `GET /api/v1/products/{id}` — карточка товара с изображениями и доступным остатком по точкам магазина.

### Защищённые маршруты (`auth:api`, право `catalog.write` — роль `admin`, проверка RS256 JWT локально через `ecommerce/platform-kit`)

- `POST /api/v1/stores`, `PUT|PATCH /api/v1/stores/{store}`, `DELETE /api/v1/stores/{store}` — управление магазинами (деактивация запрещена при активных резервах — `409`).
- `POST /api/v1/categories`, `PUT|PATCH /api/v1/categories/{category}`, `DELETE /api/v1/categories/{category}` — управление категориями.
- `POST /api/v1/products`, `PUT|PATCH /api/v1/products/{product}`, `DELETE /api/v1/products/{product}` — управление товарами (единый сервис `CreateProduct` и общие правила `ProductRules` с Filament).
- `PUT|PATCH /api/v1/products/{product}/stock` — изменение физического остатка товара на точке (не ниже текущего `reserved_quantity`).
- `POST /api/v1/products/{product}/images`, `DELETE /api/v1/images/{image}` — загрузка и удаление изображений товара в MinIO.

Swagger UI (`/api/documentation`, спецификация в `app/OpenApi/`) доступен только в окружении `APP_ENV=local`. Ошибки API возвращаются в формате RFC 9457 (`application/problem+json`).

---

## Kafka, Transactional Outbox и команды

### Публикуемые топики (через `catalog_outbox` и `php artisan catalog:relay-outbox`)

- `catalog.events`:
  - `product.created`, `product.updated`, `product.deleted` — синхронизация индекса `search-service` и снимков товаров в `order-service`.
  - `category.created`, `category.updated`, `category.deleted` — изменения дерева категорий (при переносе ветки переиздаются `product.updated` с новым `category_path`).
  - `stock.changed` — агрегированный и по-точечный доступный остаток товара.
- `inventory.events`:
  - `stock.reserved`, `stock.reservation_failed`, `stock.committed`, `stock.commit_failed` — ответы саги резервирования и списания для `order-service`.

### Входящие топики и консьюмеры (ручной commit оффсетов, retry с backoff, DLQ)

- `order.events` (`php artisan catalog:consume-order-events`, DLQ: `order.events.dlq.catalog-service`) — резервирование при `order.created`, списание при `order.paid`, освобождение при `order.cancelled`.
- `stock.commands` (`php artisan catalog:consume-stock-commands`, DLQ: `stock.commands.dlq.catalog-service`) — команды саги `stock.commit_requested` и `stock.release_requested`.

### Прочие консольные команды и планировщик

- `php artisan catalog:relay-outbox {--once} {--batch=50}` — отправка накопленных записей `catalog_outbox` в Kafka в порядке `id`.
- `php artisan catalog:release-expired-reservations` — ежеминутное снятие просроченных броней старше `inventory.reservation_ttl_minutes` (строго больше окна оплаты `inventory.payment_ttl_minutes`).
- `php artisan catalog:reindex-products` — публикация `product.updated` по всем товарам через `ProductEventPublisher`.
- `php artisan catalog:replay-dlq {topic} {--replay}` — просмотр и повторная отправка сообщений из DLQ.

---

## Админ-панель Filament (`/catalog-admin`)

Панель администратора доступна по адресу `/catalog-admin`. Middleware `AuthenticateCatalogAdmin` проверяет RS256 access-токен локально (из заголовка `Authorization: Bearer` или cookie `__Host-access` / `access_token`) через `AccessTokenVerifier` без сетевых обращений к `auth-service`, требует роль `admin` и привязывает сессию запроса к детерминированному `AdminUser`.

---

## Проверка качества и тесты

Все тесты написаны на Pest (`Unit`, `Feature`, `Integration`, `Arch`).

```bash
composer lint          # Laravel Pint (проверка стиля)
composer refactor      # Rector (dry-run)
composer analyse       # PHPStan (уровень 8 + Larastan)
composer test          # Pest (Unit, Feature, Arch без группы pgsql)
composer check         # Полный прогон: lint + refactor + analyse + test
```

Интеграционные тесты инвариантов PostgreSQL запускаются с `DB_CONNECTION=pgsql`:

```bash
DB_CONNECTION=pgsql php artisan test --group=pgsql
```
