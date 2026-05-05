# syntax=docker/dockerfile:1.7

FROM php:8.4-fpm AS base

ARG UID=1000
ARG GID=1000

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    PHP_MEMORY_LIMIT=512M \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=1

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        bash ca-certificates curl git unzip zip \
        libpq-dev libzip-dev libicu-dev libonig-dev libssl-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath intl mbstring opcache pcntl pdo_pgsql sockets zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/app.ini

RUN groupadd -g ${GID} app \
    && useradd -u ${UID} -g app -m -s /bin/bash app \
    && chown -R app:app /var/www/html

USER app

FROM base AS dev
CMD ["php-fpm"]

FROM base AS prod
USER root
COPY --chown=app:app apps/web /var/www/html
USER app
RUN if [ -f composer.json ]; then composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader; fi
CMD ["php-fpm"]