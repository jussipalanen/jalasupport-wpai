# syntax=docker/dockerfile:1

# -----------------------------------------------------------------------------
# Versions. Override with --build-arg or the matching variables in .env
# (docker-compose.yml passes them through).
# -----------------------------------------------------------------------------
# PHP version for all stages: newest stable release with a mature ecosystem.
# PHP 8.6 was tested and is not supported yet (WP-CLI deprecations, image
# without mbstring). Keep in sync with the CI PHP matrix.
ARG PHP_VERSION=8.5
# Composer (official composer image tag).
ARG COMPOSER_VERSION=2.11.2
# WP-CLI release (https://github.com/wp-cli/wp-cli/releases).
ARG WP_CLI_VERSION=2.12.0
# Node.js major version used to build the theme assets (production image).
ARG NODE_VERSION=22

FROM composer:${COMPOSER_VERSION} AS composer

# -----------------------------------------------------------------------------
# Base image: PHP + Apache with the extensions WordPress needs.
# -----------------------------------------------------------------------------
FROM php:${PHP_VERSION}-apache AS base

# Build the PHP extensions, then keep only the runtime libraries they link
# against and remove the -dev packages (pattern from the official PHP images).
# DL4006: no pipefail on purpose; dpkg-query exits non-zero for files no
# package owns. SC2086: $extensions must split into separate names.
# hadolint ignore=DL4006,SC2086
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends unzip less default-mysql-client; \
    savedAptMark="$(apt-mark showmanual)"; \
    apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    extensions="exif gd intl mysqli pdo_mysql zip"; \
    # OPcache is built into PHP 8.5+; only older versions need the extension.
    if ! php -m | grep -qi '^Zend OPcache$'; then extensions="$extensions opcache"; fi; \
    docker-php-ext-install -j"$(nproc)" $extensions; \
    apt-mark auto '.*' > /dev/null; \
    apt-mark manual $savedAptMark > /dev/null; \
    find /usr/local -type f -name '*.so' -exec ldd '{}' ';' \
        | awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); printf "*%s\n", so }' \
        | sort -u \
        | xargs -r dpkg-query --search \
        | cut -d: -f1 \
        | sort -u \
        | xargs -r apt-mark manual; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    a2enmod rewrite headers expires; \
    rm -rf /var/lib/apt/lists/*; \
    php -m | grep -qiE '^(gd|intl|mysqli|zip|exif|pdo_mysql)$'

COPY docker/php/conf.d/ /usr/local/etc/php/conf.d/
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Composer
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

# WP-CLI, verified against the published SHA-512 checksum (sha512sum is the
# last command of the pipe, so a mismatch fails the build).
ARG WP_CLI_VERSION
# hadolint ignore=DL4006
RUN base="https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar" \
    && curl -fsSL -o /usr/local/bin/wp "${base}" \
    && echo "$(curl -fsSL "${base}.sha512")  /usr/local/bin/wp" | sha512sum -c - \
    && chmod +x /usr/local/bin/wp

WORKDIR /var/www/html

# -----------------------------------------------------------------------------
# Development image: source is bind-mounted, dependencies installed on start.
# -----------------------------------------------------------------------------
FROM base AS dev

# Match www-data to the host user so bind-mounted files stay writable.
ARG UID=1000
ARG GID=1000
RUN groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g "${GID}" www-data \
    && mkdir -p /var/www/.composer /var/www/.wp-cli \
    && chown -R www-data:www-data /var/www

# The local MySQL container uses a self-signed certificate: wrap the MySQL /
# MariaDB client tools used by WP-CLI (wp db query/export/import/check) so they keep
# TLS but skip certificate verification.
COPY docker/php/mysql-client-wrapper.sh /usr/local/bin/mysql-client-wrapper
RUN chmod +x /usr/local/bin/mysql-client-wrapper \
    && for tool in mariadb mysql mariadb-dump mysqldump mariadb-check mysqlcheck; do \
        ln -s /usr/local/bin/mysql-client-wrapper "/usr/local/bin/${tool}"; \
    done

COPY docker/php/docker-entrypoint.sh /usr/local/bin/jalasupport-entrypoint
RUN chmod +x /usr/local/bin/jalasupport-entrypoint

ENTRYPOINT ["jalasupport-entrypoint"]
CMD ["apache2-foreground"]

# -----------------------------------------------------------------------------
# Production build: Composer dependencies without dev packages.
# -----------------------------------------------------------------------------
FROM base AS build

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction \
    # Bedrock uses web/app as the content directory; drop the bundled defaults.
    && rm -rf web/wp/wp-content

# -----------------------------------------------------------------------------
# Production build: theme assets (Vite).
# -----------------------------------------------------------------------------
FROM node:${NODE_VERSION}-bookworm-slim AS assets

WORKDIR /app/web/app/themes/jalasupport

COPY web/app/themes/jalasupport/package.json web/app/themes/jalasupport/package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY web/app/themes/jalasupport/ ./
RUN npm run build

# -----------------------------------------------------------------------------
# Production image: code and dependencies baked in, no bind mounts.
# Persistent data (uploads, Wordfence wflogs) must be mounted as volumes.
# -----------------------------------------------------------------------------
FROM base AS prod

ENV WP_ENV=production

COPY docker/php/conf.d-production/ /usr/local/etc/php/conf.d/

COPY --from=build /var/www/html /var/www/html
COPY --from=assets /app/web/app/themes/jalasupport/public/build /var/www/html/web/app/themes/jalasupport/public/build

# Writable runtime directories, plus a static health check endpoint that does
# not load WordPress or touch the database.
RUN mkdir -p web/app/uploads web/app/wflogs web/app/cache \
    && chown -R www-data:www-data web/app/uploads web/app/wflogs web/app/cache \
    && printf 'ok\n' > web/healthz.txt

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD ["curl", "-fsS", "-o", "/dev/null", "http://localhost/healthz.txt"]

EXPOSE 80
CMD ["apache2-foreground"]

