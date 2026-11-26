# Recommendation Service

Микросервис AI-рекомендаций и консультационного чата интернет-магазина (Symfony 8.1, PHP 8.5, PostgreSQL + `pgvector`, Kafka, Redis, Google Gemini API).

## Назначение и архитектура

Сервис поддерживает локальную векторную проекцию товарного каталога (`product_projections` с индексом `HNSW` по косинусному расстоянию) и предоставляет консультационный чат для авторизованных покупателей:

1. **Индексация каталога (`catalog.events`)** — консьюмер Symfony Messenger (`kafka_catalog`) читает события `product.created`, `product.updated`, `product.published`, `product.unpublished`, `product.deleted`, `stock.changed` и `category.*`.
   - `App\Service\ProductIndexer` объединяет подготовку текста (`ProductTextBuilder`), генерацию 768-мерного эмбеддинга (`LlmClientInterface`) и запись в `pgvector` (`ProductProjectionRepository::upsertProjection`). Этот же сервис используется консольной командой полной переиндексации `recommendation:reindex-products`.
   - Для событий `stock.changed` обновляется только флаг наличия (`is_in_stock`) без обращения к Gemini API.
2. **Семантический подбор и консультация (`RecommendationEngine`)**:
   - Из сообщения покупателя через LLM извлекаются мягкие фильтры (`category`, `attributes`) и жёсткий потолок цены (`price_max`), список категорий ограничивается реальными категориями выбранного магазина (`CategoryCatalog`).
   - В `pgvector` выполняется векторный поиск с локальным режимом `hnsw.iterative_scan` и переранжированием кандидатов по совпадению категории и характеристик; при пустой выборке с мягкими фильтрами выполняется повторный запрос с сохранением `price_max`.
   - Ответ ассистента формируется по найденным карточкам (Grounded Generation), очищается от внешних ссылок и Markdown-разметки (`AssistantReply::sanitize`) и атомарно сохраняется вместе с сообщением пользователя в транзакции.
3. **Очистка неактивных диалогов (Symfony Scheduler)**:
   - Команда `recommendation:cleanup-conversations` помечена атрибутом `#[AsPeriodicTask('1 minute')]` и запускается воркером расписания `php bin/console messenger:consume scheduler_default`, удаляя диалоги по истечении срока неактивности (`CONVERSATION_TTL_MINUTES`, по умолчанию `30` минут) или абсолютного TTL (`24` часа).

## HTTP API

Все маршруты имеют префикс `/recommendations` и при ошибках возвращают `application/problem+json` (RFC 9457):

- `GET /recommendations/health` — публичная проверка доступности сервиса (`recommendations.health`).
- `POST /recommendations/chat` — отправка сообщения в AI-чат магазина (`recommendations.chat`, требуется Bearer JWT RS256). Действует ограничение частоты запросов (`ChatQuota`: 10 сообщений в минуту и 40 в сутки на пользователя, при превышении — `429 Too Many Requests` с заголовком `Retry-After`).
- `GET /recommendations/conversations` — список активных диалогов текущего пользователя (`recommendations.conversations.list`).
- `GET /recommendations/conversations/{id}` — история сообщений диалога (`recommendations.conversations.show`, проверка владельца — чужой диалог отвечает `404`).
- `DELETE /recommendations/conversations/{id}` — удаление диалога (`recommendations.conversations.delete`).

OpenAPI-спецификация описана атрибутами `zircote/swagger-php` в `src/OpenApi/OpenApiSpec.php`.

## Переменные окружения

Основные параметры задаются через окружение (значения по умолчанию без секретов находятся в `.env`):

- `DATABASE_URL` — строка подключения к PostgreSQL с расширением `vector`.
- `REDIS_URL` — подключение к Redis для `rate_limiter`.
- `KAFKA_BROKERS`, `KAFKA_CATALOG_TOPIC`, `KAFKA_CONSUMER_GROUP` — параметры Kafka-консьюмера каталога.
- `GEMINI_API_KEY`, `GEMINI_EMBEDDING_MODEL`, `GEMINI_CHAT_MODEL` — ключ и модели Google Gemini API.
- `SIMILARITY_THRESHOLD` — минимальный порог косинусного сходства (по умолчанию `0.57`).
- `CONVERSATION_TTL_MINUTES` — время жизни диалога без активности в минутах (по умолчанию `30`).
- `JWT_JWKS_URL` / `JWT_JWKS_PATH`, `JWT_ISSUER`, `JWT_AUDIENCE` — параметры проверки RS256 access-токенов через `ecommerce/platform-kit`.

## Консольные команды и воркеры

```bash
# Воркер индексации событий каталога из Kafka
php bin/console messenger:consume kafka_catalog --time-limit=3600 --memory-limit=256M

# Воркер планировщика (очистка истёкших диалогов каждую минуту)
php bin/console messenger:consume scheduler_default --time-limit=3600 --memory-limit=128M

# Ручная переиндексация локальной проекции товаров
php bin/console recommendation:reindex-products

# Разовый запуск очистки просроченных диалогов
php bin/console recommendation:cleanup-conversations
```

## Проверки качества и тесты

```bash
# Полный набор проверок (PHP-CS-Fixer, Rector, PHPStan L8, lint:container, lint:yaml, PHPUnit)
composer check

# Отдельные команды
composer lint
composer refactor
composer analyse
composer test
```
