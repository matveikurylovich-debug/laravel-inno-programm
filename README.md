# Laravel RBAC (Spatie + Inertia React)

Проект с реализацией ролевой модели доступа (RBAC) на базе Laravel 11, Spatie Permission и Inertia.js (React).

## Запуск проекта через Docker (Laravel Sail)

1. Клонировать репозиторий и перейти в директорию:
   ```bash
   git clone <URL_РЕПОЗИТОРИЯ>
   cd <ПАПКА_ПРОЕКТА>
   ```

2. Установить зависимости Composer:
   ```bash
   composer install
   ```

3. Настроить окружение:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Запустить контейнеры:
   ```bash
   ./vendor/bin/sail up -d
   ```

5. Накатить миграции и запустить сидер:
   ```bash
   ./vendor/bin/sail artisan migrate --seed
   ```

6. Установить фронтенд-зависимости и запустить сборщик:
   ```bash
   ./vendor/bin/sail npm install
   ./vendor/bin/sail npm run dev
   ```

Приложение доступно по адресу: `http://localhost`.

---

## Тестовые учетные данные

| Роль | Email | Пароль | Доступ к `/admin` |
|---|---|---|---|
| **Administrator** | `admin@example.com` | `password` | Разрешен |
| **User** | `user@example.com` | `password` | Запрещен (403 / скрыто меню) |
