# Base image: docker build -t ecommerce-php-base:8.5 -f infra/php/Dockerfile infra/php
FROM ecommerce-php-base:8.5 AS vendor

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --no-progress \
        --no-scripts \
        --optimize-autoloader

FROM ecommerce-php-base:8.5

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

RUN mkdir -p var/cache var/log \
    && composer dump-autoload --optimize --no-dev --no-scripts \
    && chown -R www-data:www-data var

USER www-data
