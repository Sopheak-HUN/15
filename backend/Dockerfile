FROM php:8.4-fpm-alpine

# Set working directory
WORKDIR /var/www

# Install system dependencies & PHP extensions
RUN apk add --no-cache \
    bash \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    icu-dev \
    postgresql-dev \
    oniguruma-dev

# Use official php extension installer helper script
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Install PHP extensions required by Laravel & stancl/tenancy
RUN install-php-extensions \
    pdo_pgsql \
    redis \
    intl \
    zip \
    bcmath \
    xml \
    dom \
    gd \
    opcache

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy existing application directory contents
COPY . /var/www

# Create vendor, storage, and bootstrap/cache directories and adjust permissions for www-data user
RUN mkdir -p /var/www/vendor /var/www/bootstrap/cache /var/www/storage \
    && chown -R www-data:www-data /var/www

# Set user context
USER www-data

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]
