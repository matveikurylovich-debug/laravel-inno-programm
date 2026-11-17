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

RUN composer dump-autoload --optimize --no-dev --no-scripts
