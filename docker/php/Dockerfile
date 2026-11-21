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

FROM composer:${COMPOSER_VERSION} AS composer

# -----------------------------------------------------------------------------
# Base image: PHP + Apache with the extensions WordPress needs.
# -----------------------------------------------------------------------------
FROM php:${PHP_VERSION}-apache AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        unzip \
        less \
        default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    # OPcache is built into PHP 8.5+; only older versions need the extension.
    && extensions="exif gd intl mysqli pdo_mysql zip" \
    && if ! php -m | grep -qi '^Zend OPcache$'; then extensions="$extensions opcache"; fi \
    && docker-php-ext-install -j"$(nproc)" $extensions \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/conf.d/ /usr/local/etc/php/conf.d/
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Composer
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

# WP-CLI, verified against the published SHA-512 checksum.
ARG WP_CLI_VERSION
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
