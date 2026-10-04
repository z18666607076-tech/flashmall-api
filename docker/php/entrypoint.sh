#!/bin/sh
set -eu

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

attempts=0
until php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); } catch (Throwable $e) { fwrite(STDERR, $e->getMessage().PHP_EOL); exit(1); }'; do
    attempts=$((attempts + 1))
    if [ "$attempts" -ge 30 ]; then
        echo "MySQL did not become ready in time." >&2
        exit 1
    fi
    sleep 1
done

php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction

# `php artisan serve` only forwards a small env allow-list to the PHP server.
# Laravel's router uses getcwd() as the public path, so start in public/ and
# inherit the Compose environment (DB_HOST, REDIS_HOST, and the rest).
cd /var/www/html/public
exec php -S 0.0.0.0:8000 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
