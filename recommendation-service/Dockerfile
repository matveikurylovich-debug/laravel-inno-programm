# syntax=docker/dockerfile:1
# hadolint ignore=DL3006
FROM php-base AS vendor
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_MIRROR_PATH_REPOS=1
WORKDIR /var/www/html
COPY --from=platform-kit . /var/www/packages/platform-kit
COPY composer.json composer.lock symfony.lock ./
RUN --mount=type=cache,target=/root/.cache/composer \
    composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts --no-autoloader
COPY . .
# .env с дефолтами Symfony закоммичен и нужен ядру (C-REC-02): компилируем его в .env.local.php
RUN composer dump-autoload --no-dev --optimize --no-scripts \
    && composer dump-env prod

# hadolint ignore=DL3006
FROM php-base AS app
WORKDIR /var/www/html
ENV APP_ENV=prod APP_DEBUG=0 LARAVEL_CONFIG_CACHE=false
COPY --from=platform-kit . /var/www/packages/platform-kit
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/zz-www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY --from=vendor /var/www/html ./
RUN mkdir -p var/cache var/log \
    && php bin/console cache:warmup \
    && chown -R www-data:www-data var
USER www-data

FROM app AS dev
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=1 APP_ENV=dev COMPOSER_HOME=/tmp/composer

FROM app AS prod
