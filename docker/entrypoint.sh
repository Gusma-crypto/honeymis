#!/bin/sh

set -eu
umask 022

cd /var/www/html

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

key_file=storage/app/.docker-app-key
if [ -z "${APP_KEY:-}" ]; then
    if [ ! -s "$key_file" ]; then
        key="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
        printf '%s' "$key" > "$key_file.tmp"
        chmod 600 "$key_file.tmp"
        mv "$key_file.tmp" "$key_file"
    fi

    APP_KEY="$(cat "$key_file")"
    export APP_KEY
fi

php artisan config:clear
php artisan route:clear

if [ "${APP_BOOTSTRAP:-true}" = "true" ]; then
    php artisan migrate --force
    php artisan db:seed --class='Database\Seeders\LocalDemoSeeder' --force
fi

php artisan config:cache
php artisan route:cache

exec "$@"
