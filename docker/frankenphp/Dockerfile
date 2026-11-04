# syntax=docker/dockerfile:1
# Образ для режиму frankenphp. FrankenPHP вбудовує PHP у свій бінарник,
# тому базується на офіційному образі, але з тією ж версією PHP,
# тими самими розширеннями, php.ini і тим самим кодом застосунку.

ARG FRANKENPHP_VERSION=1.13
ARG PHP_VERSION=8.4

FROM composer:2 AS composer

# ---- assets: збірка фронтенду (Vite + Tailwind) ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY app/package.json app/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY app/ ./
RUN npm run build

FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}-bookworm

COPY docker/certs/ /usr/local/share/ca-certificates/extra/
RUN update-ca-certificates

RUN apt-get update \
 && apt-get install -y --no-install-recommends unzip \
 && rm -rf /var/lib/apt/lists/* \
 && install-php-extensions pdo_pgsql redis opcache pcntl sockets intl zip

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint

WORKDIR /var/www/app

COPY app/composer.json app/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY app/ ./
COPY --from=assets /app/public/build public/build
RUN composer dump-autoload --optimize --no-dev \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && touch .env

ENV SERVER_MODE=frankenphp
EXPOSE 8000
ENTRYPOINT ["entrypoint"]
