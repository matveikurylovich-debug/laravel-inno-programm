#!/usr/bin/env bash
set -euo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")"

prepare_env() {
  local env_file=".env"
  declare -A values=()
  local line key service target example tmp

  if [[ -f "$env_file" ]]; then
    while IFS= read -r line || [[ -n "$line" ]]; do
      [[ "$line" == \#* || "$line" != *=* ]] && continue
      values["${line%%=*}"]="${line#*=}"
    done < "$env_file"
  fi

  [[ -n "${values[PUBLIC_URL]:-}" ]] || values[PUBLIC_URL]="https://localhost"

  for key in JWT_ACCESS_SECRET JWT_REFRESH_SECRET RECOMMENDATION_APP_SECRET \
    AUTH_DB_PASSWORD CATALOG_DB_PASSWORD ORDER_DB_PASSWORD RECOMMENDATION_DB_PASSWORD \
    RABBITMQ_PASSWORD CLICKHOUSE_PASSWORD MINIO_PASSWORD GRAFANA_PASSWORD; do
    if [[ -z "${values[$key]:-}" ]]; then
      values[$key]="$(openssl rand -hex 32)"
    fi
  done

  for key in AUTH CATALOG ORDER SEARCH NOTIFICATION; do
    if [[ -z "${values[${key}_APP_KEY]:-}" ]]; then
      values[${key}_APP_KEY]="base64:$(openssl rand -base64 32 | tr -d '\n')"
    fi
  done

  [[ -v values[GEMINI_API_KEY] ]] || values[GEMINI_API_KEY]=""

  tmp="$(mktemp)"
  for key in "${!values[@]}"; do
    printf '%s=%s\n' "$key" "${values[$key]}"
  done > "$tmp"
  mv "$tmp" "$env_file"
  chmod 600 "$env_file"

  for service in auth catalog order search notification analytics; do
    target="${service}-service/.env"
    example="${service}-service/.env.example"
    if [[ ! -e "$target" && -f "$example" ]]; then
      cp "$example" "$target"
      chmod 600 "$target"
    fi
    if [[ "$service" != analytics && -f "$target" ]]; then
      if grep -q '^BROADCAST_CONNECTION=' "$target"; then
        sed -i 's|^BROADCAST_CONNECTION=.*|BROADCAST_CONNECTION=null|' "$target"
      else
        printf '\nBROADCAST_CONNECTION=null\n' >> "$target"
      fi
    fi
  done
}

prepare_env
if [[ "${1:-}" == "--prepare-env" ]]; then
  exit 0
fi

if [[ "${START_ENV:-local}" == "local" ]]; then
  export COMPOSE_FILE="compose.yaml:compose.override.yml"
else
  export COMPOSE_FILE="compose.yaml"
fi

if [[ ! -s infra/certs/localhost.crt || ! -s infra/certs/localhost.key ]]; then
  if ! command -v mkcert >/dev/null 2>&1; then
    printf 'mkcert is required for the local HTTPS certificate.\n' >&2
    printf 'Install it (https://github.com/FiloSottile/mkcert#installation) and run ./start.sh again.\n' >&2
    exit 1
  fi
  mkdir -p infra/certs
  mkcert -install
  mkcert -cert-file infra/certs/localhost.crt -key-file infra/certs/localhost.key localhost 127.0.0.1 ::1
  chmod 600 infra/certs/localhost.key
fi
docker compose config --quiet
docker compose --profile php-base build php-base
docker compose build
# Locally the source tree is bind-mounted over /var/www/html and hides vendor
# baked into the image. Install into that mount so the IDE can see vendor/.
if [[ "${START_ENV:-local}" == "local" ]]; then
  for app in auth-app catalog-service order-service search-app notification-app recommendation-app; do
    docker compose run --rm --no-deps "$app" composer install --no-interaction --prefer-dist --no-progress --no-scripts
    if [[ "$app" != recommendation-app ]]; then
      docker compose run --rm --no-deps "$app" php artisan package:discover
      docker compose run --rm --no-deps "$app" php artisan config:clear
    fi
  done
fi
docker compose up -d --wait auth-postgres auth-redis catalog-postgres catalog-redis order-postgres order-redis search-redis elasticsearch recommendation-pgvector mongodb kafka analytics-rabbitmq analytics-clickhouse minio mailhog
for app in auth-app catalog-service order-service; do
  docker compose run --rm --no-deps "$app" php artisan migrate --force
done
if [[ -n "${ADMIN_EMAIL:-}" ]]; then
  docker compose run --rm --no-deps auth-app php artisan auth:create-admin "$ADMIN_EMAIL"
fi
docker compose run --rm --no-deps recommendation-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose run --rm --no-deps search-app php artisan search:setup-index
docker compose run --rm --no-deps catalog-service php artisan filament:upgrade
gemini_key=""
if [[ -f .env ]]; then
  gemini_key="$(grep -E '^GEMINI_API_KEY=' .env | tail -n 1 | cut -d= -f2- | tr -d '"' | tr -d "'" || true)"
fi
if [[ -n "$gemini_key" ]]; then
  docker compose --profile gemini up -d --wait --wait-timeout 240
else
  docker compose --profile gemini stop recommendation-worker >/dev/null 2>&1 || true
  printf 'GEMINI_API_KEY is empty; recommendation-worker stays stopped.\n'
  docker compose up -d --wait --wait-timeout 240
fi
if [[ "${1:-}" == --seed ]]; then
  docker compose exec -T catalog-service php artisan db:seed --force
  docker compose exec -T catalog-service php artisan catalog:reindex-products
fi
printf 'Services started. Open https://localhost (or PUBLIC_URL from .env).\n'
