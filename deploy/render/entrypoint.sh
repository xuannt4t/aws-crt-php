#!/bin/sh
set -eu

: "${PORT:=10000}"
export PORT

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

envsubst '$PORT' \
    < /etc/nginx/templates/default.conf.template \
    > /etc/nginx/conf.d/default.conf

php artisan storage:link || true
php artisan migrate --force
php artisan optimize

exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
