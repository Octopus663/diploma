# syntax=docker/dockerfile:1
# Єдиний образ застосунку для режимів fpm, roadrunner і swoole.
# Режим обирається змінною SERVER_MODE в entrypoint, код однаковий.

ARG PHP_VERSION=8.4
ARG RR_VERSION=2025.1.15

FROM spiralscout/roadrunner:${RR_VERSION} AS rr

FROM composer:2 AS composer

FROM php:${PHP_VERSION}-fpm-bookworm AS base

# Необов'язкові корпоративні/проксі CA-сертифікати (docker/certs/*.crt).
# Зазвичай тека порожня, на звичайній машині нічого не змінює.
COPY docker/certs/ /usr/local/share/ca-certificates/extra/
RUN update-ca-certificates

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN apt-get update \
 && apt-get install -y --no-install-recommends nginx unzip \
 && rm -rf /var/lib/apt/lists/* \
 && install-php-extensions pdo_pgsql redis opcache pcntl sockets intl zip swoole

COPY --from=rr /usr/bin/rr /usr/local/bin/rr
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/fpm/www.conf /usr/local/etc/php-fpm.d/www.conf
RUN rm -f /usr/local/etc/php-fpm.d/zz-docker.conf
COPY docker/nginx/default.conf /etc/nginx/sites-enabled/default
COPY docker/roadrunner/.rr.yaml /etc/rr/.rr.yaml
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint

WORKDIR /var/www/app

# ---- prod: образ для вимірювань (без dev-залежностей) ----
FROM base AS prod

# Спершу залежності (кешується), потім код.
COPY app/composer.json app/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY app/ ./
RUN composer dump-autoload --optimize --no-dev \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && touch .env \
 && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000
ENTRYPOINT ["entrypoint"]

# ---- dev: той самий образ + dev-залежності (тести, pint) ----
# Використовується лише службовим контейнером `app`.
FROM prod AS dev
RUN composer install --prefer-dist --no-interaction --no-scripts \
 && composer dump-autoload --optimize
