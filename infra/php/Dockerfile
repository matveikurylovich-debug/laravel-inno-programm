# syntax=docker/dockerfile:1
# Базовый образ для всех PHP-сервисов. Собирается compose как сервис php-base,
# сервисные Dockerfile получают его через build.additional_contexts.
ARG PHP_IMAGE=php:8.5.12-fpm-trixie
# hadolint ignore=DL3006
FROM ${PHP_IMAGE}

# Расширения. OPcache в PHP 8.5 встроен в ядро, его не ставим (X-TOOL-01).
# -dev библиотеки и компиляторы ($PHPIZE_DEPS) удаляются в том же слое,
# runtime-библиотеки остаются (они нужны собранным .so).
# hadolint ignore=DL3008,DL4006,SC2086
RUN set -eux; \
    savedAptMark="$(apt-mark showmanual)"; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev libpng-dev libicu-dev librdkafka-dev libssl-dev; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql zip gd intl bcmath pcntl sockets; \
    pecl install redis rdkafka mongodb; \
    docker-php-ext-enable redis rdkafka mongodb; \
    rm -rf /tmp/pear ~/.pearrc; \
    apt-mark auto '.*' > /dev/null; \
    apt-mark manual $savedAptMark > /dev/null; \
    apt-mark auto $PHPIZE_DEPS > /dev/null; \
    find /usr/local -type f \( -name '*.so' -o -perm -u+x \) -exec ldd '{}' ';' 2>/dev/null \
        | awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); printf "*%s\n", so }' \
        | sort -u | xargs -r dpkg-query --search \
        | awk 'sub(":$", "", $1) { print $1 }' | sort -u | xargs -r apt-mark manual; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    apt-get install -y --no-install-recommends libfcgi-bin; \
    rm -rf /var/lib/apt/lists/*; \
    cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"; \
    php -m | grep -qi 'zend opcache'; \
    php -m | grep -q rdkafka

ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=0
COPY conf/zz-base.ini /usr/local/etc/php/conf.d/zz-base.ini
COPY conf/zz-pool.conf /usr/local/etc/php-fpm.d/zz-pool.conf
COPY --chmod=755 docker-entrypoint-laravel /usr/local/bin/docker-entrypoint-laravel

WORKDIR /var/www/html
ENTRYPOINT ["docker-entrypoint-laravel"]
CMD ["php-fpm"]
