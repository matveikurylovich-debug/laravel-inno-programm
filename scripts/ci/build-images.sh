#!/usr/bin/env bash
# Build the images compose runs.
# --load puts them in the local engine. --push publishes the registry tags.
# Service images are built together so BuildKit runs them in parallel.
set -euo pipefail

cd "$(dirname "$0")/../.."

mode="${1:-}"
registry="${IMAGE_REGISTRY:-ghcr.io/matveikurylovich-debug}"
tag="${IMAGE_TAG:-local}"
output="type=cacheonly"
if [[ "$mode" == "--load" ]]; then
  output="type=docker"
elif [[ "$mode" == "--push" ]]; then
  output="type=registry"
fi

# The container driver cannot see a just-loaded local tag, so the base image
# is handed to later builds as an OCI layout instead of docker-image://.
base_tar="$(mktemp --suffix=.tar)"
base_layout="$(mktemp -d)"
rm -f "$base_tar"
docker buildx build \
  --file infra/php/Dockerfile \
  --tag ecommerce-php-base:8.5 \
  --cache-from "type=gha,scope=php-base" \
  --cache-to "type=gha,scope=php-base,mode=max" \
  --output "type=oci,dest=${base_tar}" \
  infra/php
tar -xf "$base_tar" -C "$base_layout"

REGISTRY="$registry" TAG="$tag" OUTPUT="$output" PHP_BASE="$base_layout" \
  docker buildx bake --file docker-bake.hcl default

if [[ "$mode" == "--load" ]]; then
  docker buildx build --load --tag catalog-minio:local \
    --file catalog-service/docker/minio/Dockerfile catalog-service/docker/minio
  docker buildx build --load --tag catalog-mc:local \
    --file catalog-service/docker/minio/Dockerfile.mc catalog-service/docker/minio
fi
