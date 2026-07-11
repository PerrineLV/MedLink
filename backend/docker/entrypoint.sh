#!/bin/bash
set -e

if [ -f composer.json ]; then
    composer install --no-interaction --optimize-autoloader
fi

php-fpm -D

exec nginx -g 'daemon off;'
