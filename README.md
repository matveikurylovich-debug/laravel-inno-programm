# InnoTrainne Framework Laravel project

Монорепозиторий микросервисной архитектуры:

* **[auth-service](./auth-service)** — сервис аутентификации (Laravel 11, Breeze, JWT, Redis Blacklist & Token Rotation).
* **[notification-service](./notification-service)** — сервис уведомлений (Kafka Consumer, Pusher WebSockets, MongoDB, Mailhog).

## Секреты и pre-commit

Где хранятся секреты, описано в [SECURITY.md](./SECURITY.md).

Хук не даёт закоммитить ключ:

```bash
# установить gitleaks: https://github.com/gitleaks/gitleaks#installing
git config core.hooksPath githooks
```

Проверка всей истории: `gitleaks detect --source . --log-opts=--all --config .gitleaks.toml --redact`.
