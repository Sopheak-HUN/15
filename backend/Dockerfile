# syntax=docker/dockerfile:1.7
FROM php:8.4-fpm-alpine AS base

# Set working directory
WORKDIR /var/www

# Install system dependencies
RUN apk add --no-cache \
    bash \
    ca-certificates \
    curl \
    libpng-dev \
    libzip-dev \
    libwebp-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    unzip \
    git \
    icu-dev \
    postgresql-dev \
    oniguruma-dev \
    supervisor \
    su-exec \
    tzdata

# Use official php extension installer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Install PHP extensions required by Laravel 11+ and stancl/tenancy
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    redis \
    intl \
    zip \
    bcmath \
    xml \
    dom \
    gd \
    opcache \
    pcntl \
    sockets \
    exif

# Copy Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure PHP for production
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# ---------- Build stage: install dependencies ----------
FROM base AS build

# Copy composer files first for better layer caching
COPY composer.json composer.lock ./

# Install PHP dependencies (no dev, optimized autoloader)
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# Copy application source
COPY . .

# Run composer scripts now that the full app is present
RUN composer dump-autoload --optimize --classmap-authoritative

# ---------- Final production image ----------
FROM base AS production

# Copy application with installed vendor
COPY --from=build --chown=www-data:www-data /var/www /var/www

# Create required directories and set permissions
RUN mkdir -p \
    /var/www/storage/framework/cache \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/logs \
    /var/www/bootstrap/cache \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Entrypoint runs migrations / caches config on container start.
# Note: entrypoint.sh starts as root so it can chown the vendor named volume
# on first boot, then drops privileges to www-data via su-exec before running CMD.
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm", "-F"]
