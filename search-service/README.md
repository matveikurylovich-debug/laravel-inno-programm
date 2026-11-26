# Search Service (`ecommerce/search-service`)

Микросервис полнотекстового поиска и фасетной фильтрации товаров на базе **Laravel 13**, **PHP 8.5** и **Elasticsearch 8**.

Сервис является чисто API/worker-приложением без собственной реляционной базы данных: источником истины для товарного индекса служат события каталога из Kafka и HTTP API `catalog-service` при полной переиндексации.

---

## Возможности

- **Полнотекстовый поиск (`Elasticsearch 8`)**:
  - русская морфология (`stemmer: russian`);
  - граф поисковых синонимов (`смартфон ↔ телефон`, `ноутбук ↔ лэптоп`, `наушники ↔ гарнитура` и др.);
  - устойчивость к опечаткам (`fuzziness: AUTO`);
  - префиксный поиск по названию (`edge_ngram` 3..15);
  - поиск по вложенным характеристикам товара (`nested` поле `attributes`).
- **Фасеты и фильтрация**:
  - фильтрация по поддереву категорий через массив предков `category_path`;
  - фильтрация по диапазону цен (`price_min`, `price_max`) и наличию (`in_stock`);
  - агрегации по категориям, наличию на складе, минимальной/максимальной цене и вложенным атрибутам;
  - сортировка по релевантности (`relevance`), возрастанию (`price_asc`) и убыванию (`price_desc`) цены.
- **Отказоустойчивое кэширование (`Cache\Repository`)**:
  - кэширование результатов поиска в Redis с TTL `SEARCH_CACHE_TTL` (по умолчанию `120` сек.);
  - graceful degradation: при недоступности Redis ошибка логируется через `report()`, а запрос прозрачно обслуживается напрямую из Elasticsearch без ответа `500`.
- **Асинхронная индексация из Kafka (`catalog.events`)**:
  - обработка событий `product.created`, `product.updated`, `product.deleted`, `category.created`, `category.updated`, `category.deleted`, `stock.changed`;
  - ручное подтверждение смещений (`withManualCommit`), настраиваемые повторные попытки с паузой (`Sleep::usleep`) и маршрутизация неисправимых/исчерпавших попытки сообщений в DLQ (`catalog.events.dlq.search-service`).
- **Переиндексация без простоя (`IndexAliasPlan`)**:
  - схема индекса хранится в `resources/elasticsearch/products.json`;
  - новый версионированный индекс наполняется пакетами и атомарно переключается под алиас `products` с удалением старых индексов.
- **Телеметрия поиска**:
  - очередь `search-events` отправляет событие `search.performed` (включая `took_ms` из Elasticsearch либо `0` при попадании в кэш) через `Ecommerce\PlatformKit\Analytics\AnalyticsPublisher` в RabbitMQ.
- **Единый формат ошибок**:
  - все ошибки API возвращаются в формате RFC 9457 (`application/problem+json`).

---

## HTTP API

| Метод | Путь | Описание |
| --- | --- | --- |
| `GET` | `/api/search` | Поиск товаров, пагинация и фасеты |
| `GET` | `/up` | Healthcheck сервиса |

### Параметры `GET /api/search`

- `q` — поисковый запрос (до 200 символов);
- `category_id` — идентификатор категории (фильтрует по `category_path`, включая дочерние категории);
- `price_min`, `price_max` — границы цены (`>= 0`, `price_max >= price_min`);
- `in_stock` — фильтр наличия (`true`, `false`, `1`, `0`);
- `page` — номер страницы (`>= 1`, ограничение глубины `page * per_page <= 10000`, иначе `422`);
- `per_page` — размер страницы (`1..100`, по умолчанию `20`);
- `sort` — `relevance` | `price_asc` | `price_desc`.

OpenAPI-описание вынесено в `app/OpenApi/SearchOpenApi.php`. Swagger UI доступен только при `APP_ENV=local` (или `L5_SWAGGER_ENABLED=true`).

---

## Artisan-команды

```bash
# Создать первичный индекс и алиас products по схеме resources/elasticsearch/products.json
php artisan search:setup-index

# Собрать новый индекс из catalog-service и атомарно переключить алиас
php artisan search:reindex

# Запустить Kafka-консьюмер событий catalog.events
php artisan search:consume-catalog-events

# Просмотреть сообщения в DLQ или вернуть их в исходный топик (--replay)
php artisan search:replay-dlq catalog.events.dlq.search-service --replay
```

---

## Переменные окружения

Основные переменные описаны в `.env.example`:

- `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=array`
- `ELASTICSEARCH_HOSTS=http://elasticsearch:9200`, `ELASTICSEARCH_INDEX=products`
- `KAFKA_BROKERS=kafka:9092`, `KAFKA_TOPIC_CATALOG_EVENTS=catalog.events`, `KAFKA_DLQ_CATALOG_EVENTS=catalog.events.dlq.search-service`
- `KAFKA_FAILED_MESSAGE_RETRIES=5`, `KAFKA_FAILED_MESSAGE_BACKOFF_MS=2000`
- `CATALOG_URL=http://catalog-nginx/api`
- `ANALYTICS_RABBITMQ_URL=amqp://guest:guest@analytics-rabbitmq:5672/`, `ANALYTICS_RABBITMQ_EXCHANGE=analytics.events`

---

## Проверка качества и тесты

```bash
# Полный конвейер проверок (Pint, Rector, PHPStan Level 8, Pest без живого ES)
composer check

# Автоисправление стиля и правил Rector
composer lint:fix
composer refactor:fix

# Статический анализ PHPStan (Level 8)
composer analyse

# Модульные, функциональные и архитектурные тесты
composer test

# Интеграционные тесты с реальным Elasticsearch
ELASTICSEARCH_INTEGRATION=1 ELASTICSEARCH_HOSTS=http://127.0.0.1:9200 php artisan test --group=elasticsearch
```
