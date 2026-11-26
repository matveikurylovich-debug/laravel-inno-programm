#!/usr/bin/env bash
set -euo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")"

case "${START_ENV:-local}" in
  local) export COMPOSE_FILE="compose.yaml:compose.dev.yaml" ;;
  *) export COMPOSE_FILE="compose.yaml" ;;
esac

docker compose --profile '*' down --remove-orphans "$@"
