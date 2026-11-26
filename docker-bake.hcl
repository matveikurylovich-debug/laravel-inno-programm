variable "REGISTRY" {
  default = "ghcr.io/matveikurylovich-debug"
}

variable "TAG" {
  default = "local"
}

variable "OUTPUT" {
  default = "type=cacheonly"
}

variable "PHP_BASE" {
  default = ""
}

group "default" {
  targets = [
    "auth",
    "catalog",
    "order",
    "search",
    "notification",
    "recommendation",
    "frontend",
    "analytics",
  ]
}

target "php-app" {
  target = "prod"
  output = ["${OUTPUT}"]
  contexts = {
    php-base     = "oci-layout://${PHP_BASE}"
    platform-kit = "packages/platform-kit"
  }
}

target "auth" {
  inherits   = ["php-app"]
  context    = "auth-service"
  dockerfile = "../infra/php/laravel.Dockerfile"
  tags       = ["${REGISTRY}/ecommerce-auth:${TAG}"]
  cache-from = ["type=gha,scope=auth"]
  cache-to   = ["type=gha,scope=auth,mode=max"]
}

target "catalog" {
  inherits   = ["php-app"]
  context    = "catalog-service"
  dockerfile = "../infra/php/laravel.Dockerfile"
  tags       = ["${REGISTRY}/ecommerce-catalog:${TAG}"]
  cache-from = ["type=gha,scope=catalog"]
  cache-to   = ["type=gha,scope=catalog,mode=max"]
}

target "order" {
  inherits   = ["php-app"]
  context    = "order-service"
  dockerfile = "../infra/php/laravel.Dockerfile"
  tags       = ["${REGISTRY}/ecommerce-order:${TAG}"]
  cache-from = ["type=gha,scope=order"]
  cache-to   = ["type=gha,scope=order,mode=max"]
}

target "search" {
  inherits   = ["php-app"]
  context    = "search-service"
  dockerfile = "../infra/php/laravel.Dockerfile"
  tags       = ["${REGISTRY}/ecommerce-search:${TAG}"]
  cache-from = ["type=gha,scope=search"]
  cache-to   = ["type=gha,scope=search,mode=max"]
}

target "notification" {
  inherits   = ["php-app"]
  context    = "notification-service"
  dockerfile = "../infra/php/laravel.Dockerfile"
  tags       = ["${REGISTRY}/ecommerce-notification:${TAG}"]
  cache-from = ["type=gha,scope=notification"]
  cache-to   = ["type=gha,scope=notification,mode=max"]
}

target "recommendation" {
  inherits   = ["php-app"]
  context    = "recommendation-service"
  dockerfile = "Dockerfile"
  tags       = ["${REGISTRY}/ecommerce-recommendation:${TAG}"]
  cache-from = ["type=gha,scope=recommendation"]
  cache-to   = ["type=gha,scope=recommendation,mode=max"]
}

target "frontend" {
  context    = "frontend"
  dockerfile = "Dockerfile"
  tags       = ["${REGISTRY}/ecommerce-frontend:${TAG}"]
  cache-from = ["type=gha,scope=frontend"]
  cache-to   = ["type=gha,scope=frontend,mode=max"]
  output     = ["${OUTPUT}"]
}

target "analytics" {
  context    = "analytics-service"
  dockerfile = "Dockerfile"
  tags       = ["${REGISTRY}/ecommerce-analytics:${TAG}"]
  cache-from = ["type=gha,scope=analytics"]
  cache-to   = ["type=gha,scope=analytics,mode=max"]
  output     = ["${OUTPUT}"]
}
