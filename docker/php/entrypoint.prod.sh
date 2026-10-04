#!/bin/sh
set -eu

cd /var/www/html

if [ "${1:-php-fpm}" != "php-fpm" ]; then
    exec "$@"
fi

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is empty. Generate one on the server and set it in .env.prod." >&2
    exit 1
fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

attempts=0
until php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); } catch (Throwable $e) { fwrite(STDERR, $e->getMessage().PHP_EOL); exit(1); }'; do
    attempts=$((attempts + 1))
    if [ "$attempts" -ge 30 ]; then
        echo "MySQL did not become ready in time." >&2
        exit 1
    fi
    sleep 2
done

php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

exec php-fpm -F
