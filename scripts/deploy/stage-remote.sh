#!/usr/bin/env bash
# Runs on the stage host. Pulls images already pushed to GHCR, dumps databases,
# migrates, then rolls back to the previous tag if the gateway smoke fails.
set -euo pipefail

tag="${1:?image tag}"
cd "${STAGE_APP_DIR:-$HOME/app}"
export COMPOSE_FILE=compose.yaml
export IMAGE_TAG="$tag"
export IMAGE_REGISTRY="${IMAGE_REGISTRY:-ghcr.io/matveikurylovich-debug}"

set_env_var() {
  local key="$1" value="$2"
  touch .env
  chmod 600 .env
  if grep -qE "^${key}=" .env; then
    sed -i "s|^${key}=.*|${key}=${value}|" .env
  else
    printf '%s=%s\n' "$key" "$value" >> .env
  fi
}

if [[ -f .env ]]; then
  set -a
  # shellcheck disable=SC1091
  source .env
  set +a
fi

previous="${IMAGE_TAG:-}"
if [[ -f .last-good-tag ]]; then
  previous="$(cat .last-good-tag)"
fi

set_env_var COMPOSE_FILE compose.yaml
set_env_var IMAGE_TAG "$tag"

rollback() {
  if [[ -z "$previous" || "$previous" == "$tag" ]]; then
    echo "smoke failed and there is no previous tag to roll back to" >&2
    exit 1
  fi
  echo "smoke failed, rolling back to ${previous}" >&2
  export IMAGE_TAG="$previous"
  set_env_var IMAGE_TAG "$previous"
  docker compose pull
  docker compose up -d --wait --wait-timeout 300 --no-build
  exit 1
}

docker compose pull

stamp="$(date -u +%Y%m%dT%H%M%SZ)"
backup="${HOME}/backups/${stamp}"
mkdir -p "$backup"

dump() {
  local service="$1" user="$2" database="$3" password="$4"
  docker compose exec -T -e "PGPASSWORD=${password}" "$service" \
    pg_dump -U "$user" -d "$database" >"${backup}/${database}.sql"
}

dump auth-postgres auth auth_db "${AUTH_DB_PASSWORD:-}"
dump catalog-postgres catalog catalog_db "${CATALOG_DB_PASSWORD:-}"
dump order-postgres order order_db "${ORDER_DB_PASSWORD:-}"
dump recommendation-pgvector app_user recommendation_db "${RECOMMENDATION_DB_PASSWORD:-}"

for app in auth-app catalog-app order-app notification-app; do
  docker compose run --rm --no-deps "$app" php artisan migrate --force
done
docker compose run --rm --no-deps recommendation-app php bin/console doctrine:migrations:migrate --no-interaction

docker compose up -d --wait --wait-timeout 300 --no-build

gateway="https://127.0.0.1:${GATEWAY_HTTPS_PORT:-443}"
if ! curl -kfsS "${gateway}/health" >/dev/null || ! curl -kfsS "${gateway}/api/catalog/v1/stores" >/dev/null || [[ -n "$(docker compose ps --status restarting -q)" ]]; then
  rollback
fi

printf '%s\n' "$tag" >.last-good-tag
echo "stage is on ${tag}"
