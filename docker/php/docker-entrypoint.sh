#!/bin/sh
# Development entrypoint: install Composer dependencies on first start so that
# `docker compose up -d` is enough to get a working environment.
set -e

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
    echo "[jalasupport] Installing Composer dependencies..."
    su www-data -s /bin/sh -c "composer install --no-interaction --prefer-dist"
fi

exec docker-php-entrypoint "$@"
