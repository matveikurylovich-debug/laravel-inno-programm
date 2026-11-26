# Правила разработки и проверки кода (`CONTRIBUTING.md`)

## 1. Обязательные проверки перед push и PR

Ни один коммит не отправляется без локального прогона полного гейта в затронутых сервисах:

- **Laravel-сервисы (`auth`, `catalog`, `order`, `notification`, `search`) и `recommendation-service` (Symfony):**
  ```bash
  composer check
  ```
  Команда последовательно запускает форматирование (`pint --test` / `php-cs-fixer check`), Rector (`--dry-run`), PHPStan Level 8 с baseline и тесты (Pest / PHPUnit, включая сьют `Arch`).
  - Число записей в `phpstan-baseline.neon` может **только уменьшаться** — в CI стоит автоматическая проверка против базовой ветки.

- **`packages/platform-kit`:**
  ```bash
  cd packages/platform-kit && composer check
  ```

- **`analytics-service` (Go):**
  ```bash
  cd analytics-service
  test -z "$(gofmt -l .)"
  go mod tidy -diff
  go vet ./...
  go test -race ./...
  ```

- **`frontend` (Next.js 16):**
  ```bash
  cd frontend
  npm audit --omit=dev --audit-level=high
  npm run check
  npm run build
  ```

- **Контракты межсервисных событий и конфигурация Docker Compose:**
  ```bash
  php scripts/ci/verify-contracts.php
  COMPOSE_FILE=compose.yaml docker compose config --quiet
  COMPOSE_FILE=compose.yaml:compose.dev.yaml docker compose config --quiet
  ```

## 2. Единые соглашения по коду и архитектуре

1. **Структура папок и неймспейсов** зафиксирована в [`docs/architecture/layout.md`](./docs/architecture/layout.md) и проверяется в каждом сервисе через `tests/Arch/LayoutTest.php`.
2. **Строгая типизация:** каждый PHP-файл обязан начинаться с `declare(strict_types=1);`.
3. **DI вместо статики и service location:** в прикладном коде (`app/Services`, `app/Messaging`, `app/Http`) не используются вызовы `app(...)`, `new StripeClient`, сырой `Redis::` фасад и `time()`.
4. **Ошибки HTTP API** отдаются в формате RFC 9457 (`application/problem+json`) со стабильным машинным `code` и русскоязычным `detail`.
5. **Логирование:** сообщение в `Log::info` / `warning` / `error` всегда статично на английском языке, все переменные и исключение передаются массивом контекста (`['order_id' => $id, 'exception' => $exception]`). Комментарии и документация ведутся на русском языке; комментарии объясняют «зачем», а не пересказывают код.
6. **Секреты:** приватные ключи, пароли и токены никогда не коммитятся в git (`git config core.hooksPath githooks` включает pre-commit проверку `gitleaks`).
