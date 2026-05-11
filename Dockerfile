# syntax=docker/dockerfile:1

# FrankenPHP speaks HTTP, so your existing Nginx `proxy_pass` workflow works.
# This tag floats within FrankenPHP major `1`, PHP `8.5`, on Debian Bookworm.
FROM dunglas/frankenphp:1-php8.5-bookworm AS base

# Bind to port 9004 (unprivileged) and disable auto-HTTPS since Nginx is in front.
ENV SERVER_NAME=":9004"
ENV CADDY_AUTO_HTTPS=off

# The FrankenPHP image provides install-php-extensions.
RUN install-php-extensions mbstring zip

EXPOSE 9004

# Composer (copy from official image).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app


FROM base AS dev

# Dev target: sources are expected to be mounted via volume at /app.
# No COPY and no build-time composer install here.


FROM base AS prod

# Prod-only extension: OPcache for performance.
RUN install-php-extensions opcache

# Install deps at build time for deterministic startup.
# Copy only the manifest files first to maximize Docker layer cache hits.
COPY composer.json composer.lock symfony.lock /app/
RUN composer install --no-interaction --no-dev --no-scripts --prefer-dist --optimize-autoloader

COPY . /app

# App runtime directories.
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var

