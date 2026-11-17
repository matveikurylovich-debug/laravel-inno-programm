FROM php:8.5-fpm

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libicu-dev \
        librdkafka-dev \
        libssl-dev \
    && docker-php-ext-install pdo_pgsql pgsql zip gd intl bcmath pcntl sockets opcache \
    && pecl install redis rdkafka mongodb \
    && docker-php-ext-enable redis rdkafka mongodb opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN printf '%s\n' \
        'opcache.enable=1' \
        'opcache.enable_cli=1' \
        'opcache.validate_timestamps=1' \
        'opcache.revalidate_freq=0' \
        'opcache.memory_consumption=128' \
        'opcache.max_accelerated_files=10000' \
        > /usr/local/etc/php/conf.d/opcache.ini \
    && printf 'clear_env = no\n' > /usr/local/etc/php-fpm.d/zz-env.conf
