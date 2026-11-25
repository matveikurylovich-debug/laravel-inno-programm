#!/usr/bin/env bash
# Prepare env, boot compose without the local override, then hit the gateway.
set -euo pipefail

cd "$(dirname "$0")/../.."
export COMPOSE_FILE=compose.yaml
export IMAGE_TAG="${IMAGE_TAG:-local}"
export IMAGE_REGISTRY="${IMAGE_REGISTRY:-ghcr.io/matveikurylovich-debug}"

./start.sh --prepare-env

if [[ ! -s infra/certs/localhost.crt || ! -s infra/certs/localhost.key ]]; then
  mkdir -p infra/certs
  openssl req -x509 -nodes -newkey rsa:2048 \
    -keyout infra/certs/localhost.key \
    -out infra/certs/localhost.crt \
    -days 2 -subj "/CN=localhost"
  chmod 600 infra/certs/localhost.key
fi

./scripts/ci/build-images.sh --load
docker compose up -d --wait --wait-timeout 300 --no-build

for app in auth-app catalog-service order-service; do
  docker compose run --rm --no-deps "$app" php artisan migrate --force
done

gateway="https://127.0.0.1:${HTTPS_PORT:-443}"
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
