# syntax=docker/dockerfile:1.6

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --ignore-platform-req=ext-gd --ignore-platform-req=php

FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor /app/vendor
RUN npm run build

FROM serversideup/php:8.4-fpm-nginx

USER root

RUN install-php-extensions \
    bcmath \
    gd \
    intl \
    pdo_sqlite \
    pcntl \
    zip

WORKDIR /var/www/html

COPY --chown=www-data:www-data . /var/www/html
COPY --from=vendor --chown=www-data:www-data /app/vendor /var/www/html/vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build /var/www/html/public/build

RUN composer dump-autoload --optimize --no-dev --working-dir=/var/www/html \
    && mkdir -p \
        /var/www/html/bootstrap/cache \
        /var/www/html/storage/framework/cache \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/logs \
        /var/www/html/database \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache /var/www/html/storage /var/www/html/database \
    && chmod -R 775 /var/www/html/bootstrap/cache /var/www/html/storage /var/www/html/database

COPY --chmod=755 docker/app/entrypoint.d/ /etc/entrypoint.d/

USER www-data
