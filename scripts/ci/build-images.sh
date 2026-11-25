#!/usr/bin/env bash
# Build the images compose runs. --load keeps them in the local docker
# engine for the smoke job. The GHA cache is shared with the images job.
set -euo pipefail

cd "$(dirname "$0")/../.."

mode="${1:-}"
registry="${IMAGE_REGISTRY:-ghcr.io/matveikurylovich-debug}"
tag="${IMAGE_TAG:-local}"
cache_to="type=gha,mode=max"
output="type=cacheonly"
if [[ "$mode" == "--load" ]]; then
  output="type=docker"
elif [[ "$mode" == "--push" ]]; then
  output="type=registry"
fi

build() {
  local scope="$1" image="$2" file="$3" context="$4"
  echo "building ${image}"
  docker buildx build \
    --file "$file" \
    --tag "$image" \
    --cache-from "type=gha,scope=${scope}" \
    --cache-to "${cache_to},scope=${scope}" \
    --output "$output" \
    "$context"
}

build php-base ecommerce-php-base:8.5 infra/php/Dockerfile infra/php
build auth "${registry}/ecommerce-auth:${tag}" auth-service/Dockerfile .
build catalog "${registry}/ecommerce-catalog:${tag}" catalog-service/Dockerfile .
build order "${registry}/ecommerce-order:${tag}" order-service/Dockerfile .
build search "${registry}/ecommerce-search:${tag}" search-service/Dockerfile .
build notification "${registry}/ecommerce-notification:${tag}" notification-service/Dockerfile .
build recommendation "${registry}/ecommerce-recommendation:${tag}" recommendation-service/Dockerfile recommendation-service
build frontend "${registry}/ecommerce-frontend:${tag}" frontend/Dockerfile frontend
build analytics "${registry}/ecommerce-analytics:${tag}" analytics-service/backend/Dockerfile analytics-service/backend
build minio catalog-minio:local catalog-service/docker/minio/Dockerfile catalog-service/docker/minio
build minio-mc catalog-mc:local catalog-service/docker/minio/Dockerfile.mc catalog-service/docker/minio
