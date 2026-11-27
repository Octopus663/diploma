#!/bin/sh
# Точка входу: однаковий код, різний сервер застосунку.
# SERVER_MODE: fpm | roadrunner | swoole | frankenphp | cli
set -e

cd /var/www/app

if [ "$#" -gt 0 ]; then
    # Довільна команда, наприклад: docker compose run --rm app php artisan test
    exec "$@"
fi

# Кеш конфігурації, роутів і подій (APP_ENV=production).
if [ "${APP_CACHE:-true}" = "true" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan event:cache
fi

WORKERS="${OCTANE_WORKERS:-2}"
MAX_REQUESTS="${OCTANE_MAX_REQUESTS:-500}"

case "${SERVER_MODE:-fpm}" in
    fpm)
        php-fpm -D
        exec nginx -g 'daemon off;'
        ;;
    roadrunner)
        exec php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=8000 \
            --rr-config=/etc/rr/.rr.yaml --workers="$WORKERS" --max-requests="$MAX_REQUESTS"
        ;;
    swoole)
        exec php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000 \
            --workers="$WORKERS" --task-workers=0 --max-requests="$MAX_REQUESTS"
        ;;
    frankenphp)
        exec php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000 \
            --admin-port=2019 --workers="$WORKERS" --max-requests="$MAX_REQUESTS"
        ;;
    *)
        echo "Невідомий SERVER_MODE: ${SERVER_MODE}" >&2
        exit 1
        ;;
esac
