# syntax=docker/dockerfile:1

# -----------------------------------------------------------------------------
# Base image: PHP + Apache with the extensions WordPress needs.
# -----------------------------------------------------------------------------
FROM php:8.3-apache AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        unzip \
        default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" exif gd intl mysqli opcache pdo_mysql zip \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/conf.d/ /usr/local/etc/php/conf.d/
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Composer and WP-CLI
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN curl -fsSL -o /usr/local/bin/wp https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar \
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

COPY docker/php/docker-entrypoint.sh /usr/local/bin/jalasupport-entrypoint
RUN chmod +x /usr/local/bin/jalasupport-entrypoint

ENTRYPOINT ["jalasupport-entrypoint"]
CMD ["apache2-foreground"]
