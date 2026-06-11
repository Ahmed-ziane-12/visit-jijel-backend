FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --optimize-autoloader

FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    nginx \
    supervisor \
    postgresql-dev \
    linux-headers \
    autoconf \
    gcc \
    g++ \
    make \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        bcmath \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && pecl clear-cache \
    && ln -s /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini \
    && sed -i 's|listen = 9000|listen = 127.0.0.1:9000|' /usr/local/etc/php-fpm.d/zz-docker.conf

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN php artisan route:cache && \
    php artisan view:cache && \
    php artisan config:cache && \
    php artisan event:cache && \
    chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
