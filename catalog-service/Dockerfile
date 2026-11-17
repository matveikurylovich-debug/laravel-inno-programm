# Base image: docker build -t ecommerce-php-base:latest -f infra/php/Dockerfile infra/php
FROM ecommerce-php-base:latest AS vendor

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --no-progress \
        --no-scripts \
        --optimize-autoloader

FROM ecommerce-php-base:latest

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor

RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --optimize --no-dev --no-scripts \
    && APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php artisan package:discover --ansi
