# InnoTrainne Frontend

Клиентское веб-приложение интернет-магазина на базе **Next.js 16** (App Router), **React 19** и **Tailwind CSS 4**.

## Архитектура

- **`proxy.ts`** — серверная защита маршрутов Next.js 16 (`/profile`, `/orders`, `/checkout`, `/assistant`, `/admin/:path*`). Проверяет наличие access-cookie (`__Host-access` или `access_token` в локальной HTTP-разработке) и перенаправляет гостей на `/login?next=<encoded-pathname>`.
- **`app/providers/SessionProvider.tsx`** — единый источник сессии пользователя (`useSession()`), выполняющий не более одного запроса `GET /api/auth/me` на загрузку страницы и различающий состояния `loading`, `authenticated`, `guest` (`401`) и `unavailable` (`5xx` / сетевой сбой).
- **`app/lib/api.ts` и `app/lib/api/*`** — типизированный HTTP-слой (`auth`, `catalog`, `orders`, `notifications`, `search`, `recommendations`, `analytics`) с разбором `application/problem+json` (`readError`), поддержкой `AbortController` (`useAsyncQuery`) и single-flight обновлением сессии.
- **`app/lib/permissions.ts`** — клиентские политики доступа (`canViewAdmin`, `canManageUsers`, `canCancelOrder`, `canReplayNotification`, `canOpenCatalogAdmin`), синхронизированные с правилами микросервисов.
- **`app/lib/cart.ts`** — корзина в `localStorage`, изолированная по ключу `cart:${userId ?? "guest"}` с автоматическим переключением и очисткой гостевого контекста при выходе.

## Сессия и cookies

Токены никогда не сохраняются в `localStorage`. Браузер работает через `HttpOnly` cookies:

- `__Host-access` (`Path=/`) — короткоживущий access-токен, проксируемый API-шлюзом в заголовок `Authorization: Bearer`.
- `refresh_token` (`Path=/api/auth`) — refresh-cookie с ограниченным путём; при ответе `401` клиентский `apiFetch` выполняет единый (`single-flight` + `navigator.locks`) запрос `POST /api/auth/refresh` и повторяет исходный запрос.

## Локальная разработка

Требуется **Node.js >= 22.18** (рекомендуется Node 24 из `.nvmrc`).

```bash
cp .env.example .env.local
npm ci
npm run dev
```

По умолчанию `next.config.ts` проксирует `/api/auth/*` и `/api/*` на `AUTH_INTERNAL_URL` (`http://127.0.0.1:8000`).

## Сборка и Docker (Standalone)

Приложение собирается в режиме `output: "standalone"`. Многоступенчатый `Dockerfile` (`deps` -> `build` -> `runtime`) копирует только `.next/standalone`, `.next/static` и `public`, работает от непривилегированного пользователя `node` и проверяет готовность через `HEALTHCHECK` на `http://127.0.0.1:3000/login`.

```bash
npm run build
node .next/standalone/server.js
```

## Проверки качества и тесты

```bash
npm run check
```

Команда `npm run check` последовательно запускает:

- `npm run lint` — ESLint 9 (`eslint-config-next`, `typescript-eslint` type-checked, `jsx-a11y`, `--max-warnings=0`);
- `npm run typecheck` — `next typegen && tsc --noEmit`;
- `npm run format:check` — проверка форматирования Prettier;
- `npm test` — модульные тесты на встроенном раннере `node --experimental-strip-types --test`.

Проверка безопасности зависимостей прод-сборки:

```bash
npm audit --omit=dev --audit-level=high
```
