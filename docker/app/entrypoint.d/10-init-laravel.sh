#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
    bootstrap/cache \
    database \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
fi

if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

if [ -n "${APP_KEY:-}" ]; then
    php -r '$path = ".env"; $key = getenv("APP_KEY"); $env = file_exists($path) ? file_get_contents($path) : ""; if (preg_match("/^APP_KEY=/m", $env)) { $env = preg_replace("/^APP_KEY=.*/m", "APP_KEY=" . $key, $env, 1); } else { $env .= PHP_EOL . "APP_KEY=" . $key . PHP_EOL; } file_put_contents($path, $env);'
fi

if ! grep -q '^APP_KEY=.\+' .env && [ -z "${APP_KEY:-}" ]; then
    php artisan key:generate --force --no-interaction
fi

php artisan config:clear
php artisan migrate --force --no-interaction
