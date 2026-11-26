# Auth Service — Headless Laravel 13 API Guidelines

## Context

- `auth-service` is a headless JSON REST API running on PHP 8.5 and Laravel 13 (`ecommerce/auth-service`).
- There is no server-rendered UI or frontend asset pipeline in this service; all HTTP endpoints live under `/api/v1/*` (plus `/up` healthcheck) and return JSON or `application/problem+json` via `Ecommerce\PlatformKit\Laravel\ProblemJson`.
- Follow the platform layout rules enforced by `tests/Arch/LayoutTest.php`.

## Architecture & Conventions

- Every PHP file in `app/` and `tests/` must declare `declare(strict_types=1);`, use explicit parameter and return types, and prefer constructor property promotion.
- Controllers live in `App\Http\Controllers\Api\V1\*Controller` and stay thin; OpenAPI attributes belong in `App\OpenApi\*`, never inline on controllers.
- Form requests live in `App\Http\Requests\Api\V1\{Auth,Profile,Admin}\*Request`.
- Domain ports live in `App\Contracts\*` (without `Interface` suffix). Application services live in `App\Services\Auth\*` (never directly in `App\Services\`) and receive dependencies via constructor injection (`RefreshTokenStore`, `Cache\Repository`, `Events\Dispatcher`, `#[Config]`), avoiding facades, `app()`, and `time()`.
- Domain exceptions implement `Ecommerce\PlatformKit\Http\ProvidesProblem` and `Ecommerce\PlatformKit\Http\HasProblemCode`.
- Broker producers and messages live in `App\Messaging\{Producers,Messages}`.
- Use static log messages with structured context arrays (`['exception' => $exception, ...]`).
- Every API route must have URI prefix `api/v1` and route name prefix `api.v1.`.

## Testing & Quality Gates

- All tests in `tests/{Unit,Feature,Arch}` are written in Pest syntax.
- `tests/Unit` must remain pure, fast, and independent of external services (`ArrayRefreshTokenStore`, no database or Redis network calls).
- Run `composer check` (`@lint`, `@refactor`, `@analyse`, `@test`) before finishing any change:
  - `composer lint` / `composer lint:fix` — Laravel Pint (`../pint.json`)
  - `composer refactor` / `composer refactor:fix` — Rector
  - `composer analyse` — PHPStan Level 8 with Larastan
  - `composer test` — Pest (`Unit`, `Feature`, `Arch`)
