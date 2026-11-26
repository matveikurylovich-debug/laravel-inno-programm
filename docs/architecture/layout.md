# Правила раскладки и именования сервисов (`layout.md`)

Единый стандарт структуры кода для всех микросервисов платформы. Проверяется автоматически через Pest Arch (`tests/Arch/LayoutTest.php`) в Laravel-сервисах и PHPStan/phpat в Symfony-сервисе.

## 1. Целевая раскладка Laravel-сервисов (`auth`, `catalog`, `order`, `notification`, `search`)

```text
<service>-service/
├── app/
│   ├── Console/Commands/          <Verb><Noun>Command, signature <service>:<verb>-<noun>
│   ├── Contracts/                 порты без суффикса Interface: ProductIndex, PaymentGateway, TokenService
│   ├── Data/                      final readonly DTO <Noun>Data: CreateOrderData, RegisterUserData
│   ├── Enums/                     RoleName, OrderStatus, CatalogEventType
│   ├── Events/                    события в прошедшем времени, без суффикса Event: UserRegistered, NotificationDispatched
│   ├── Exceptions/                доменные исключения (при необходимости реализуют ProvidesProblem / HasProblemCode)
│   ├── Http/
│   │   ├── Controllers/Api/V1/    <Resource>Controller
│   │   ├── Controllers/Webhooks/  StripeWebhookController
│   │   ├── Middleware/            Ensure* / Authenticate* / Resolve* / Start*, без суффикса Middleware
│   │   ├── Requests/Api/V1/<Resource>/<Action><Resource>Request
│   │   └── Resources/             <Model>Resource
│   ├── Integrations/<Vendor>/     адаптеры внешних SDK/протоколов: Elasticsearch/, Stripe/, Redis/
│   ├── Jobs/                      фоновые задания очереди
│   ├── Listeners/                 <Verb><What>, без транспорта в имени (Publish*, Queue*)
│   ├── Mail/<Domain>/             Mailable-классы (notification-service)
│   ├── Messaging/
│   │   ├── Consumers/             <Topic>Consumer — точка входа топика
│   │   ├── Handlers/<Topic>/      <Action>On<Event> — обработчик одного события
│   │   ├── Producers/             <Topic>Producer
│   │   └── Messages/              <Event>Message / payload-классы
│   ├── Models/ (+ Concerns/)
│   ├── Observers/  Policies/  Providers/  Rules/
│   ├── OpenApi/                   OpenAPI-схемы и атрибуты (не раздувают контроллеры)
│   ├── Services/<Domain>/         прикладная логика и сценарии; в корне Services/ классов нет
│   └── Support/                   контекст запроса и чистые хелперы (CurrentStore, SensitivePayload)
├── config/{kafka.php, services.php}
├── docker/{nginx/default.conf, php/php.ini, php/zz-www.conf}
├── tests/{Unit, Feature, Integration, Arch}
└── .dockerignore, .env.example, README.md, AGENTS.md, CLAUDE.md (= @AGENTS.md)
```

### Ключевые границы слоёв

1. **`App\Messaging`, а не `App\Kafka` или `App\Services\Kafka`.** Весь брокерный код консьюмеров, маршрутизации и продюсеров топиков живёт в `App\Messaging\{Consumers,Handlers,Producers,Messages}`.
2. **`App\Integrations\<Vendor>` против `App\Services\<Domain>`.** Класс, знающий внешний SDK или протокол (`Elastic\Elasticsearch`, `Stripe`, HTTP API внешнего провайдера), лежит в `App\Integrations\<Vendor>` и реализует порт из `App\Contracts`. Класс, работающий через стандартные контракты фреймворка (`Storage`, `Cache`, Eloquent), лежит в `App\Services\<Domain>`. В корне `App\Services` файлы не размещаются.
3. **Запрещённые корневые неймспейсы в `app/`:** `App\Kafka`, `App\Search`, `App\Infrastructure`, `App\Actions`, `App\DTO`, `App\DTOs`. Сценарии оформляются в `App\Services\<Domain>\<Verb><Noun>`.
4. **Теневые проекции моделей** называются явно по своей роли (например, `App\Models\AdminUser` и таблица `admin_users` в `catalog-service`, чтобы не путать с источником истины `App\Models\User` в `auth-service`).

## 2. Соответствие Laravel ↔ Symfony (`recommendation-service`)

Каталоги Symfony именуются по стандарту фреймворка (`src/Controller`, `src/Entity`, `src/Command`, `src/Contracts`, `src/Integration`).

| Роль | Laravel (`app/`) | Symfony (`recommendation-service/src/`) |
|---|---|---|
| Консольные команды | `Console/Commands/<Verb><Noun>Command` (`<svc>:<verb>-<noun>`) | `Command/<Verb><Noun>Command` (`#[AsCommand('recommendation:<verb>-<noun>')]`) |
| Контроллеры | `Http/Controllers/Api/V1/<Resource>Controller` | `Controller/<Resource>Controller` (`final`, наследует `AbstractController`, `#[Route('/recommendations', name: 'recommendations.')]`) |
| Валидация входящего запроса | `Http/Requests/Api/V1/<Resource>/<Action><Resource>Request` | `Dto/<Action>Request` (`ChatRequest`) + `#[MapRequestPayload]` |
| Аутентификация | guard `api` из `ecommerce/platform-kit` | `Security/` (`JwksAccessTokenHandler`) |
| Входящие сообщения | `Messaging/Consumers`, `Messaging/Handlers` | `Message/`, `MessageHandler/`, `Serializer/` |
| Порты | `Contracts/<Name>` (без `Interface`) | `Contracts/<Name>Interface` (с `Interface`) |
| Адаптеры внешних систем | `Integrations/<Vendor>/` | `Integration/<Vendor>/` (`Integration\Gemini\GeminiLlmClient`) |
| Прикладная логика | `Services/<Domain>/` | `Service/` (`RecommendationEngine`, `ProductIndexer`, `ChatQuota`) |
| Данные | `Models/` | `Entity/` + `Repository/` |
| Enum | `Enums/` | `Enum/` |
| DTO и результаты | `Data/<Noun>Data` (`final readonly`) и value objects | `Dto/` (`readonly`: `SearchFilters`, `ChatResponse`, `CatalogProductEvent`) |
| Периодические задачи | `Schedule::command(...)` в `routes/console.php` | `Scheduler/` (`#[AsSchedule]`, `#[AsPeriodicTask]`) |
| Конфиг Kafka | `config/kafka.php` (`topics`, `consumers`) | `config/packages/messenger.yaml` (`%env(KAFKA_TOPIC_CATALOG_EVENTS)%`, `%env(KAFKA_GROUP_CATALOG_EVENTS)%`) |

## 3. Раскладка Go-сервиса (`analytics-service`)

Код расположен в корне `analytics-service/` (без лишней вложенности `backend/`):

```text
analytics-service/
├── cmd/api/main.go
├── internal/
│   ├── analytics/
│   ├── broker/rabbitmq/
│   ├── config/
│   ├── domain/
│   ├── http/{handlers,middleware,problem}/
│   └── storage/clickhouse/
├── docker/clickhouse/
└── Dockerfile, .dockerignore, .golangci.yml, go.mod, go.sum, README.md
```

## 4. Правила именования

| Роль | Шаблон | Пример |
|---|---|---|
| Консьюмер топика | `<Topic>Consumer` | `OrderEventsConsumer`, `CatalogEventsConsumer` |
| Обработчик события | `<Action>On<Event>` | `ReserveStockOnOrderCreated`, `CommitStockOnOrderPaid` |
| Продюсер | `<Topic>Producer` | `CatalogEventsProducer`, `OrderEventsProducer`, `AuthEventsProducer` |
| Команда | `<Verb><Noun>Command`, signature `<service>:<verb>-<noun>` | `ConsumeOrderEventsCommand` (`catalog:consume-order-events`) |
| FormRequest | `<Action><Resource>Request` (`Index`, `Store`, `Update`, `Destroy`, ...) | `StoreOrderRequest`, `UpdateProfileRequest` |
| API Resource | `<Model>Resource` | `OrderResource`, `NotificationLogResource`, `UserResource` |
| DTO между слоями | `<Noun>Data` (`final readonly`) | `CreateOrderData`, `SearchCriteriaData`, `ReservationItemData` |
| Событие | Прошедшее время, без суффикса `Event` | `PasswordResetLinkRequested`, `NotificationDispatched` |
| Listener | `<Verb><What>`, без имени транспорта | `PublishTwoFactorCodeRequested`, `QueueUserRegisteredDeliveries` |
| Middleware | `Ensure*`, `Authenticate*`, `Resolve*`, `Start*`, без суффикса `Middleware` | `EnsureIdempotentRequest`, `AuthenticateCatalogAdmin`, `StartBrowserSession` |
| Маршруты API | `Route::prefix('v1')->name('api.v1.')` (или имя `api.v1.*`), у каждого маршрута задано имя | `api.v1.notifications.index`, `recommendations.chat` |
| Топики и DLQ | `<domain>.events`, `<domain>.commands`, DLQ `<topic>.dlq.<consumer-service>` | `order.events.dlq.catalog-service`, `auth.events.dlq.notification-service` |
| Группы консьюмеров | `<consumer-service>.<topic-slug>` в `config/kafka.php` | `notification-service.order-events`, `search-service.catalog-events` |
