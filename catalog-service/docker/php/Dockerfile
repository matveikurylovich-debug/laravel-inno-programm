FROM php:8.5-fpm

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libicu-dev \
        librdkafka-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql pgsql zip gd intl bcmath pcntl \
    && pecl install redis rdkafka \
    && docker-php-ext-enable redis rdkafka \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/catalog.ini
