# InnoTrainne

Монорепозиторий магазина: auth, catalog, order, search, notification, recommendation, analytics и frontend.

## Запуск с нуля

Нужны Docker, Docker Compose и [mkcert](https://github.com/FiloSottile/mkcert#installation).

```bash
git clone <url-репозитория> shop
cd shop
./start.sh
```

Скрипт собирает базовый образ `ecommerce-php-base:8.5`, генерирует локальный TLS-сертификат в `infra/certs` и поднимает стек. Сайт: `https://localhost`.

Повторный запуск той же командой. Остановка: `./stop.sh`.

## Ротация ключа access-токена

Access-токены подписывает только `auth-app` ключом RS256. Публичные ключи отдаёт `GET /api/internal/jwks`. В JWKS могут одновременно лежать два `kid`: текущий и предыдущий.

1. Текущий ключ лежит в `.secrets/jwt/current.pem`, публичная часть в `current.pub.pem`, `JWT_ACCESS_KID=auth-1`.
2. Чтобы выпустить новый ключ, переименуйте `current.pem` и `current.pub.pem` в `previous.pem` и `previous.pub.pem`. Запомните старый `kid` и задайте `JWT_ACCESS_PREVIOUS_KID`.
3. Сгенерируйте новую пару (`openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out .secrets/jwt/current.pem` и `openssl pkey -pubout`) и смените `JWT_ACCESS_KID` на новое значение, например `auth-2`.
4. Запустите `JWT_ACCESS_KID=auth-2 JWT_ACCESS_PREVIOUS_KID=auth-1 php scripts/jwt/write-jwks.php .secrets/jwt` и перезапустите `auth-app`.
5. Старые токены проверяются по предыдущему `kid` до своего `exp`. Новые подписываются новым `kid`. Когда старые истекли, удалите `previous.pub.pem` и `JWT_ACCESS_PREVIOUS_KID`.

Закрытый ключ монтируется только в `auth-app`. Остальные сервисы ходят за JWKS и не получают `JWT_ACCESS_SECRET`.

## Секреты и pre-commit

Где хранятся секреты, описано в [SECURITY.md](./SECURITY.md).

Хук не даёт закоммитить ключ:

```bash
# установить gitleaks: https://github.com/gitleaks/gitleaks#installing
git config core.hooksPath githooks
```

Проверка всей истории: `gitleaks detect --source . --log-opts=--all --config .gitleaks.toml --redact`.

## Оплата Stripe локально

В корневой `.env` нужны тестовые ключи из [Stripe Dashboard](https://dashboard.stripe.com/test/apikeys): `STRIPE_SECRET_KEY` (`sk_test_...`) и `STRIPE_PUBLISHABLE_KEY` (`pk_test_...`). Плейсхолдеры `./start.sh` не принимает и сам ключи не генерирует.

`./start.sh` поднимает `stripe-cli` и записывает `STRIPE_WEBHOOK_SECRET` в `.env`. Ключ для формы оплаты приходит в ответе заказа, в сборку фронтенда его класть не нужно.

Проверка: корзина → checkout → карта `4242 4242 4242 4242`, любая будущая дата и любой CVC. Заказ переходит в `paid` / `confirmed`, в логе `stripe-cli` ответ вебхука `200`.
