FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        nginx \
        bash \
        curl \
        git \
        unzip \
        icu-dev \
        libzip-dev \
        postgresql-dev \
    && docker-php-ext-install intl pdo_pgsql opcache \
    && docker-php-ext-enable opcache \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/medlink.ini
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

COPY . .

EXPOSE 80

HEALTHCHECK --interval=10s --timeout=3s --start-period=20s --retries=5 \
    CMD curl -f http://localhost/healthz || exit 1

ENTRYPOINT ["/entrypoint.sh"]
