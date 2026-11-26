# syntax=docker/dockerfile:1
# Один Dockerfile для всех 5 Laravel-сервисов: build.context = каталог сервиса,
# build.dockerfile = ../infra/php/laravel.Dockerfile, php-base и platform-kit — из additional_contexts.

# 1. vendor: composer есть только здесь, в рантайм он не попадает
# hadolint ignore=DL3006
FROM php-base AS vendor
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_MIRROR_PATH_REPOS=1
WORKDIR /var/www/html
COPY --from=platform-kit . /var/www/packages/platform-kit
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/root/.cache/composer \
    composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

# 2. app: код root:root (только чтение), писать можно лишь в storage и bootstrap/cache
# hadolint ignore=DL3006
FROM php-base AS app
WORKDIR /var/www/html
COPY --from=platform-kit . /var/www/packages/platform-kit
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/zz-www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
RUN set -eux; \
    mkdir -p bootstrap/cache storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs \
        resources/views; \
    export APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=; \
    php artisan package:discover --ansi; \
    php artisan route:cache; \
    php artisan view:cache; \
    php artisan event:cache; \
    chown -R www-data:www-data storage bootstrap/cache
USER www-data

# 3. dev: composer и перечитывание файлов для bind-mount (compose.dev.yaml: target: dev)
FROM app AS dev
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=1 LARAVEL_CONFIG_CACHE=false COMPOSER_HOME=/tmp/composer

# 4. prod — последняя стадия, собирается по умолчанию
FROM app AS prod
