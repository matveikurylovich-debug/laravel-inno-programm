# Base image: docker build -t ecommerce-php-base:8.5 -f infra/php/Dockerfile infra/php
FROM ecommerce-php-base:8.5 AS vendor

WORKDIR /var/www/html

COPY packages/platform-kit /var/www/packages/platform-kit
COPY order-service/composer.json order-service/composer.lock ./

RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --no-progress \
        --no-scripts \
        --optimize-autoloader

FROM ecommerce-php-base:8.5

WORKDIR /var/www/html

COPY --from=vendor /var/www/packages /var/www/packages
COPY --chown=www-data:www-data order-service/ .
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY order-service/docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --optimize --no-dev --no-scripts \
    && APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
