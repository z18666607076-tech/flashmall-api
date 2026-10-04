FROM php:8.4-cli-bookworm

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_mysql bcmath intl zip opcache redis \
    && apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer install --no-interaction --prefer-dist \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
        vendor/pestphp/pest-plugin-mutate/.temp/pest-mutate-cache \
        vendor/pestphp/pest/.temp \
    && chown -R www-data:www-data storage bootstrap/cache \
        vendor/pestphp/pest-plugin-mutate/.temp \
        vendor/pestphp/pest/.temp

USER www-data

EXPOSE 8000

ENTRYPOINT ["docker/php/entrypoint.sh"]
