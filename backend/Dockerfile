FROM php:8.3-fpm-alpine AS base

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
    CMD curl -f http://localhost/health || exit 1

ENTRYPOINT ["/entrypoint.sh"]

# Stage dev : ajoute PCOV pour mesurer la couverture de tests PHPUnit (ML-123).
# Jamais présent dans l'image de prod (stage par défaut ci-dessous) pour ne
# pas l'alourdir ni y exposer un outil de profiling inutile.
FROM base AS dev
RUN apk add --no-cache --virtual .build-deps ${PHPIZE_DEPS} \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del .build-deps

FROM base AS prod
