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
