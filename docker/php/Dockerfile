FROM php:8.4-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    git \
    curl \
    zip \
    unzip \
    autoconf \
    g++ \
    make \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    libxml2-dev \
    oniguruma-dev \
    icu-dev \
    libzip-dev \
    supervisor

# PHP extensions
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        xml \
        bcmath \
        zip \
        intl \
        opcache \
        pcntl \
        gd \
        exif

# Redis extension via PECL
RUN pecl install redis && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node.js 20 (for npm run build inside container)
RUN apk add --no-cache nodejs npm

# Non-root user for file permission safety (matches host uid 1000)
RUN addgroup -g 1000 laravel && adduser -u 1000 -G laravel -s /bin/sh -D laravel

WORKDIR /var/www/html

# php.ini
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

USER laravel

EXPOSE 9000
CMD ["php-fpm"]
