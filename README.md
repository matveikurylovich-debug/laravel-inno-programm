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
