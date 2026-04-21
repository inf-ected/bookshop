# ─────────────────────────────────────────────────────────────────────────────
# NODE-BUILDER — compiles frontend assets (Vite 7 + Tailwind CSS v4)
# Completely isolated from the PHP build — no Node in the PHP image.
# Output: public/build/ (versioned CSS/JS + manifest.json)
# ─────────────────────────────────────────────────────────────────────────────
FROM node:22-alpine AS node-builder

WORKDIR /var/www

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources/ ./resources/

RUN npm run build


# ─────────────────────────────────────────────────────────────────────────────
# BASE — PHP 8.4 runtime with required extensions
# Shared by all subsequent stages
# ─────────────────────────────────────────────────────────────────────────────
FROM php:8.4-fpm AS base

ARG HOST_UID=1000
ARG HOST_GID=1000
RUN usermod -u ${HOST_UID} www-data \
    && groupmod -g ${HOST_GID} www-data

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        libzip-dev \
        zip \
        libxml2-dev \
        libicu-dev \
        default-mysql-client \
        pandoc \
        calibre \
    && docker-php-ext-install pdo_mysql zip xml dom intl opcache bcmath pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

RUN mkdir -p /var/log/php \
    && chown -R www-data:www-data /var/log/php


# ─────────────────────────────────────────────────────────────────────────────
# BUILDER — installs Composer dependencies with proper layer caching
#
# Cache strategy:
#   1. Copy manifests only → composer install (cached until composer.json/lock changes)
#   2. Copy full source    → dump-autoload  (only re-runs when code changes)
#
# Result: a code-only change does NOT re-download all packages.
# ─────────────────────────────────────────────────────────────────────────────
FROM base AS builder

WORKDIR /var/www

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

COPY . .
# --no-scripts prevents post-autoload-dump from running `package:discover`,
# which would fail because dev-only providers (e.g. PailServiceProvider) are
# absent with --no-dev. Laravel regenerates the package manifest at runtime.
RUN composer dump-autoload --optimize --no-dev --no-scripts


# ─────────────────────────────────────────────────────────────────────────────
# APP — production PHP-FPM image
#
# php.ini and www.conf are NOT baked in here.
# They are bind-mounted at runtime via docker-compose, keeping the image neutral
# and allowing dev/prod configs to differ without rebuilding.
# ─────────────────────────────────────────────────────────────────────────────
FROM base AS app

WORKDIR /var/www

COPY --from=builder /var/www /var/www
# Bake compiled frontend assets into the image so the @vite directive can
# resolve manifest.json without a host build step.
COPY --from=node-builder /var/www/public/build /var/www/public/build

RUN chown -R www-data:www-data \
        /var/www/storage \
        /var/www/bootstrap/cache

USER www-data

CMD ["php-fpm"]


# ─────────────────────────────────────────────────────────────────────────────
# NGINX-STATIC — production nginx image with public/ assets baked in
#
# Used by docker-compose.prod.yml so nginx has static assets without needing
# a bind-mount or shared volume. Dev uses plain nginx:alpine with a bind-mount.
# ─────────────────────────────────────────────────────────────────────────────
FROM nginx:alpine AS nginx-static

COPY --from=builder /var/www/public /var/www/public
COPY --from=node-builder /var/www/public/build /var/www/public/build
