#!/usr/bin/env bash
set -e

echo "=== 1. Проверка общей сети microservices-net ==="
docker network inspect microservices-net >/dev/null 2>&1 || docker network create microservices-net

echo "=== 2. Запуск Notification Service (Kafka, Mongo, MailHog) ==="
(cd notification-service && ./vendor/bin/sail up -d)

echo "=== 3. Запуск Auth Service (PostgreSQL, Redis, Web) ==="
(cd auth-service && ./vendor/bin/sail up -d)

echo ""
echo "=== ВСЕ СЕРВИСЫ ЗАПУЩЕНЫ ==="
echo "Frontend:             http://localhost:3000"
echo "Auth Service:         http://localhost:8000"
echo "Notification Service: http://localhost:8001"
echo "MailHog:              http://localhost:8025"
echo ""
echo "=== 4. Запуск Frontend (Next.js :3000) ==="
(cd frontend && npm run dev)
