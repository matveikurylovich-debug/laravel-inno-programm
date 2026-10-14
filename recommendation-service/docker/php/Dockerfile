FROM php:8.4-fpm

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        librdkafka-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql sockets \
    && pecl install rdkafka \
    && docker-php-ext-enable rdkafka \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/recommendation.ini
COPY docker/php/zz-www.conf /usr/local/etc/php-fpm.d/zz-www.conf
