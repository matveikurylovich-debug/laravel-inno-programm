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
  shift 4
  echo "building ${image}"
  docker buildx build \
    --file "$file" \
    --tag "$image" \
    --cache-from "type=gha,scope=${scope}" \
    --cache-to "${cache_to},scope=${scope}" \
    --output "$output" \
    "$@" \
    "$context"
}

# The container driver cannot see a just-loaded local tag, so the base image
# is handed to later builds as an OCI layout instead of docker-image://.
base_tar="$(mktemp --suffix=.tar)"
base_layout="$(mktemp -d)"
rm -f "$base_tar"
docker buildx build \
  --file infra/php/Dockerfile \
  --tag ecommerce-php-base:8.5 \
  --cache-from "type=gha,scope=php-base" \
  --cache-to "${cache_to},scope=php-base" \
  --output "type=oci,dest=${base_tar}" \
  infra/php
tar -xf "$base_tar" -C "$base_layout"

php_contexts=(
  --build-context "php-base=oci-layout://${base_layout}"
  --build-context "platform-kit=./packages/platform-kit"
)

build auth "${registry}/ecommerce-auth:${tag}" infra/php/laravel.Dockerfile auth-service --target prod "${php_contexts[@]}"
build catalog "${registry}/ecommerce-catalog:${tag}" infra/php/laravel.Dockerfile catalog-service --target prod "${php_contexts[@]}"
build order "${registry}/ecommerce-order:${tag}" infra/php/laravel.Dockerfile order-service --target prod "${php_contexts[@]}"
build search "${registry}/ecommerce-search:${tag}" infra/php/laravel.Dockerfile search-service --target prod "${php_contexts[@]}"
build notification "${registry}/ecommerce-notification:${tag}" infra/php/laravel.Dockerfile notification-service --target prod "${php_contexts[@]}"
build recommendation "${registry}/ecommerce-recommendation:${tag}" recommendation-service/Dockerfile recommendation-service --target prod "${php_contexts[@]}"
build frontend "${registry}/ecommerce-frontend:${tag}" frontend/Dockerfile frontend
build analytics "${registry}/ecommerce-analytics:${tag}" analytics-service/Dockerfile analytics-service
build minio catalog-minio:local catalog-service/docker/minio/Dockerfile catalog-service/docker/minio
build minio-mc catalog-mc:local catalog-service/docker/minio/Dockerfile.mc catalog-service/docker/minio
