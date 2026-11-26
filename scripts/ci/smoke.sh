#!/usr/bin/env bash
# Prepare env, boot compose without the local dev override, then hit the gateway.
set -euo pipefail

cd "$(dirname "$0")/../.."
export START_ENV=ci
export COMPOSE_FILE=compose.yaml
export IMAGE_TAG="${IMAGE_TAG:-local}"
export IMAGE_REGISTRY="${IMAGE_REGISTRY:-ghcr.io/matveikurylovich-debug}"

./start.sh --prepare-env

# Test-format Stripe keys so order-app boot check passes in CI smoke
printf 'STRIPE_SECRET_KEY=sk_test_SmokeCiKey1234567890ABCDEF\nSTRIPE_PUBLISHABLE_KEY=pk_test_SmokeCiKey1234567890ABCDEF\n' >> .env

if [[ ! -s infra/certs/localhost.crt || ! -s infra/certs/localhost.key ]]; then
  mkdir -p infra/certs
  openssl req -x509 -nodes -newkey rsa:2048 \
    -keyout infra/certs/localhost.key \
    -out infra/certs/localhost.crt \
    -days 2 -subj "/CN=localhost"
  chmod 600 infra/certs/localhost.key
fi

./scripts/ci/build-images.sh --load

# Workers query application tables as soon as they start. Migrate first.
# Dependencies (postgres, redis, mongo, elasticsearch) come up with the run;
# the worker services stay down until the full stack starts below.
for app in auth-app catalog-app order-app notification-app; do
  docker compose run --rm --no-build "$app" php artisan migrate --force
done
docker compose run --rm --no-build recommendation-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose run --rm --no-build search-app php artisan search:setup-index

docker compose up -d --wait --wait-timeout 300 --no-build

gateway="https://127.0.0.1:${GATEWAY_HTTPS_PORT:-443}"
curl_ok() {
  local path="$1"
  local code
  code="$(curl -kfsS -o /tmp/smoke-body -w '%{http_code}' "${gateway}${path}")"
  echo "${path} -> ${code}"
  [[ "$code" == 200 ]]
}

curl_ok /health
curl_ok /up/auth
curl_ok /up/catalog
curl_ok /up/order
curl_ok /up/search
curl_ok /up/notification
curl_ok /api/catalog/v1/stores
test -z "$(docker compose ps --status restarting -q)"
