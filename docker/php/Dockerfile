# syntax=docker/dockerfile:1.7

FROM php:8.4-fpm AS base

ARG UID=1000
ARG GID=1000
ARG PHPREDIS_VERSION=6.3.0

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    PHP_MEMORY_LIMIT=512M \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=1

WORKDIR /var/www/html

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        bash \
        ca-certificates \
        curl \
        git \
        unzip \
        zip \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libssl-dev; \
    \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        sockets \
        zip; \
    \
    curl -fsSL \
        "https://github.com/phpredis/phpredis/archive/refs/tags/${PHPREDIS_VERSION}.tar.gz" \
        -o /tmp/phpredis.tar.gz; \
    mkdir -p /tmp/phpredis; \
    tar -xzf /tmp/phpredis.tar.gz \
        --strip-components=1 \
        -C /tmp/phpredis; \
    cd /tmp/phpredis; \
    phpize; \
    ./configure --enable-redis; \
    make -j"$(nproc)"; \
    make install; \
    docker-php-ext-enable redis; \
    cd /; \
    rm -rf /tmp/phpredis /tmp/phpredis.tar.gz; \
    \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/app.ini

RUN groupadd -g "${GID}" app \
    && useradd -u "${UID}" -g app -m -s /bin/bash app \
    && chown -R app:app /var/www/html

USER app


FROM base AS dev

CMD ["php-fpm"]


FROM base AS prod

ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

USER root

COPY --chown=app:app apps/web /var/www/html

USER app

RUN composer install \
        --no-dev \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --optimize-autoloader \
    && mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache

CMD ["php-fpm"]