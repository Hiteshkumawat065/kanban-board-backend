FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
        git curl libpng-dev oniguruma-dev libxml2-dev zip unzip icu-dev \
        libzip-dev mysql-client autoconf gcc g++ make linux-headers redis \
    && docker-php-ext-install pdo_mysql mbstring xml bcmath gd intl zip pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del --no-network autoconf gcc g++ make linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY . .

RUN composer dump-autoload --optimize \
    && php artisan storage:link || true \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
