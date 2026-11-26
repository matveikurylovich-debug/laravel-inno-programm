#!/usr/bin/env bash
# Запуск платформы. START_ENV: local (по умолчанию) | ci | stage
#   local — compose.yaml + compose.dev.yaml (исходники с хоста, mailpit, порты на 127.0.0.1), mkcert
#   ci    — только compose.yaml, сертификат кладёт job (openssl)
#   stage — только compose.yaml; деплой идёт образами из registry (PR-11)
set -euo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")"

START_ENV="${START_ENV:-local}"

PHP_APPS=(
  "auth-app:laravel"
  "catalog-app:laravel"
  "order-app:laravel"
  "search-app:laravel"
  "notification-app:laravel"
  "recommendation-app:symfony"
)
ENV_FROM_EXAMPLE=(auth catalog order search notification)

prepare_env() {
  local env_file=".env" line key service target example tmp
  declare -A values=()

  if [[ -f "$env_file" ]]; then
    while IFS= read -r line || [[ -n "$line" ]]; do
      [[ "$line" == \#* || "$line" != *=* ]] && continue
      values["${line%%=*}"]="${line#*=}"
    done < "$env_file"
  fi

  [[ -n "${values[PUBLIC_URL]:-}" ]] || values[PUBLIC_URL]="https://localhost"

  for key in JWT_REFRESH_SECRET RECOMMENDATION_APP_SECRET \
    AUTH_DB_PASSWORD CATALOG_DB_PASSWORD ORDER_DB_PASSWORD RECOMMENDATION_DB_PASSWORD \
    REDIS_PASSWORD MONGO_PASSWORD RABBITMQ_PASSWORD CLICKHOUSE_PASSWORD MINIO_PASSWORD; do
    [[ -n "${values[$key]:-}" ]] || values[$key]="$(openssl rand -hex 32)"
  done

  for key in AUTH CATALOG ORDER SEARCH NOTIFICATION; do
    [[ -n "${values[${key}_APP_KEY]:-}" ]] || values[${key}_APP_KEY]="base64:$(openssl rand -base64 32 | tr -d '\n')"
  done

  [[ -v values[GEMINI_API_KEY] ]] || values[GEMINI_API_KEY]=""

  if [[ "$START_ENV" == local ]]; then
    values[LOCAL_UID]="$(id -u)"
    values[LOCAL_GID]="$(id -g)"
  fi

  tmp="$(mktemp)"
  for key in "${!values[@]}"; do
    printf '%s=%s\n' "$key" "${values[$key]}"
  done > "$tmp"
  mv "$tmp" "$env_file"
  chmod 600 "$env_file"

  for service in "${ENV_FROM_EXAMPLE[@]}"; do
    target="${service}-service/.env"
    example="${service}-service/.env.example"
    if [[ ! -e "$target" && -f "$example" ]]; then
      cp "$example" "$target"
      chmod 600 "$target"
    fi
  done
}

ensure_jwt_keys() {
  local jwt_dir=".secrets/jwt"
  mkdir -p "$jwt_dir"
  if [[ ! -f "$jwt_dir/current.pem" ]]; then
    openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out "$jwt_dir/current.pem"
    openssl pkey -in "$jwt_dir/current.pem" -pubout -out "$jwt_dir/current.pub.pem"
    chmod 600 "$jwt_dir/current.pem"
  fi
  export JWT_ACCESS_KID="${JWT_ACCESS_KID:-auth-1}"
  php scripts/jwt/write-jwks.php "$jwt_dir"
}

stripe_key_ok() {
  local value="$1" prefix="$2"
  [[ "$value" =~ ^${prefix}_[A-Za-z0-9]{16,}$ && "$value" != *placeholder* && "$value" != *example* ]]
}

env_value() {
  local key="$1" line
  [[ -f .env ]] || return 0
  line="$(grep -E "^${key}=" .env | tail -n 1 || true)"
  [[ -n "$line" ]] || return 0
  printf '%s' "${line#*=}" | tr -d '"' | tr -d "'"
}

set_env_value() {
  local key="$1" value="$2"
  if [[ -f .env ]] && grep -q "^${key}=" .env; then
    sed -i "s|^${key}=.*|${key}=${value}|" .env
  else
    printf '%s=%s\n' "$key" "$value" >> .env
  fi
}

stripe_configured() {
  stripe_key_ok "$(env_value STRIPE_SECRET_KEY)" 'sk_(test|live)' \
    && stripe_key_ok "$(env_value STRIPE_PUBLISHABLE_KEY)" 'pk_(test|live)'
}

# auth доверяет 172.28.0.0/16 как шлюзу. Эту подсеть нужно занять сетью edge
# до остальных: иначе Docker отдаёт её первой автоматически созданной сети.
ensure_edge_network() {
  local name subnet containers
  while read -r name subnet containers; do
    [[ "$subnet" == "172.28.0.0/16" ]] || continue
    [[ "$name" == "shop_edge" ]] && continue
    if [[ "${containers:-0}" != "0" ]]; then
      printf 'Подсеть 172.28.0.0/16 занята сетью %s (%s контейнеров). Освободите её и повторите ./start.sh.\n' "$name" "$containers" >&2
      exit 1
    fi
    printf 'Удаляю пустую сеть %s: подсеть 172.28.0.0/16 зарезервирована за shop_edge.\n' "$name"
    docker network rm "$name" >/dev/null
  done < <(docker network inspect $(docker network ls -q) --format '{{.Name}} {{range .IPAM.Config}}{{.Subnet}}{{end}} {{len .Containers}}')

  if docker network inspect shop_edge >/dev/null 2>&1; then
    return 0
  fi

  # Compose создаёт все сети проекта параллельно, и первая свободная 172.28.0.0/16
  # достаётся не edge. Сеть создаётся заранее с метками Compose, чтобы up её переиспользовал.
  docker network create \
    --driver bridge \
    --subnet 172.28.0.0/16 \
    --label com.docker.compose.network=edge \
    --label com.docker.compose.project=shop \
    --label com.docker.compose.version=2.40.3 \
    shop_edge >/dev/null
}

ensure_certs() {
  [[ -s infra/certs/localhost.crt && -s infra/certs/localhost.key ]] && return 0
  if [[ "$START_ENV" != local ]]; then
    printf 'infra/certs/localhost.{crt,key} не найдены. Для %s сертификат кладёт CI или ACME, mkcert только локально.\n' "$START_ENV" >&2
    exit 1
  fi
  if ! command -v mkcert >/dev/null 2>&1; then
    printf 'mkcert is required for the local HTTPS certificate: https://github.com/FiloSottile/mkcert#installation\n' >&2
    exit 1
  fi
  mkdir -p infra/certs
  mkcert -install
  mkcert -cert-file infra/certs/localhost.crt -key-file infra/certs/localhost.key localhost 127.0.0.1 ::1
  chmod 600 infra/certs/localhost.key
}

prepare_env
ensure_jwt_keys
[[ "${1:-}" == "--prepare-env" ]] && exit 0

stripe_ready=0
if [[ "$START_ENV" == local ]] && stripe_configured; then
  stripe_ready=1
elif [[ "$START_ENV" == local ]]; then
  printf 'Stripe-ключи не заданы: сайт и вход работают, оплата картой недоступна. Ключи можно добавить в .env и перезапустить.\n'
fi

case "$START_ENV" in
  local) export COMPOSE_FILE="compose.yaml:compose.dev.yaml" ;;
  ci | stage) export COMPOSE_FILE="compose.yaml" ;;
  *) printf 'Unknown START_ENV=%s (local|ci|stage)\n' "$START_ENV" >&2; exit 1 ;;
esac

ensure_certs
docker compose config --quiet
ensure_edge_network
docker compose --profile php-base build php-base
docker compose build

if [[ "$START_ENV" == local ]]; then
  for entry in "${PHP_APPS[@]}"; do
    app="${entry%%:*}"
    docker compose run --rm --no-deps "$app" composer install --no-interaction --prefer-dist --no-progress --no-scripts
    if [[ "${entry##*:}" == laravel ]]; then
      docker compose run --rm --no-deps "$app" php artisan package:discover
      docker compose run --rm --no-deps "$app" php artisan config:clear
    fi
  done
fi

infra=(auth-postgres auth-redis catalog-postgres catalog-redis order-postgres order-redis
  search-redis elasticsearch recommendation-pgvector recommendation-redis mongodb kafka
  analytics-rabbitmq analytics-clickhouse minio)
[[ "$START_ENV" == local ]] && infra+=(mailpit)
docker compose up -d --wait "${infra[@]}"

docker compose run --rm kafka-init

for app in auth-app catalog-app order-app notification-app; do
  docker compose run --rm --no-deps "$app" php artisan migrate --force
done
if [[ -n "${ADMIN_EMAIL:-}" ]]; then
  docker compose run --rm --no-deps auth-app php artisan auth:create-admin "$ADMIN_EMAIL"
fi
docker compose run --rm --no-deps recommendation-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose run --rm --no-deps search-app php artisan search:setup-index
docker compose run --rm --no-deps catalog-app php artisan filament:upgrade

profiles=()
if [[ "$stripe_ready" == 1 ]]; then
  docker compose --profile stripe up -d stripe-cli
  webhook_secret=""
  for _ in $(seq 1 30); do
    webhook_secret="$(docker compose logs stripe-cli 2>/dev/null | grep -oE 'whsec_[A-Za-z0-9]+' | tail -n 1 || true)"
    [[ -n "$webhook_secret" ]] && break
    sleep 1
  done
  if [[ -z "$webhook_secret" ]]; then
    printf 'stripe listen не выдал STRIPE_WEBHOOK_SECRET. Проверьте STRIPE_SECRET_KEY.\n' >&2
    exit 1
  fi
  set_env_value STRIPE_WEBHOOK_SECRET "$webhook_secret"
  profiles+=(--profile stripe)
fi

gemini_key="$(env_value GEMINI_API_KEY)"
if [[ -n "$gemini_key" ]]; then
  profiles+=(--profile gemini)
else
  docker compose --profile gemini stop recommendation-worker >/dev/null 2>&1 || true
  printf 'GEMINI_API_KEY is empty; recommendation-worker stays stopped.\n'
fi

docker compose "${profiles[@]}" up -d --wait --wait-timeout 300

if [[ "${1:-}" == --seed ]]; then
  docker compose exec -T catalog-app php artisan db:seed --force
  docker compose exec -T catalog-app php artisan catalog:reindex-products
fi
printf 'Services started. Open https://localhost (or PUBLIC_URL from .env).\n'
