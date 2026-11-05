#!/bin/sh
set -eu
cd /var/www/html
if [ "$(id -u)" = 0 ]; then
    mkdir -p storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
    chown www-data:www-data storage storage/app storage/app/public \
        storage/framework storage/framework/cache storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
    if [ "${1:-}" = php-fpm ]; then
        gosu www-data php artisan config:cache --no-ansi
    fi
    exec gosu www-data "$@"
fi
exec "$@"
