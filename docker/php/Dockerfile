# ---------------- BASE ----------------
FROM php:8.3-fpm AS base

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libzip-dev \
    zip \
    libxml2-dev \
    libicu-dev \
 && docker-php-ext-install pdo_mysql zip xml dom intl opcache bcmath \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

RUN mkdir -p /var/log/php \
    && chown -R www-data:www-data /var/log/php


# ---------------- BUILDER ----------------
FROM base AS builder

WORKDIR /var/www

#COPY composer.json composer.lock ./
# копируем остальной проект
COPY . .

RUN composer install --no-dev --optimize-autoloader --prefer-dist



# ---------------- APP ----------------
FROM base AS app

WORKDIR /var/www

COPY --from=builder /var/www /var/www

COPY ./docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY ./docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# только здесь переключаем пользователя
RUN chown -R www-data:www-data /var/www

USER www-data

ENTRYPOINT ["php-fpm"]
