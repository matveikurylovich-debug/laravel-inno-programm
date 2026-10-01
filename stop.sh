#!/usr/bin/env bash
echo "Остановка Auth Service..."
(cd auth-service && ./vendor/bin/sail down)

echo "Остановка Notification Service..."
(cd notification-service && ./vendor/bin/sail down)

echo "Все контейнеры остановлены."
