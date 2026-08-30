# syntax=docker/dockerfile:1

# =============================================================================
# Multi-stage build for the Laravel application.
#
#   base   -> php:8.4-fpm-alpine + only the extensions Laravel & PostgreSQL need
#   vendor -> composer install (prod deps only), run on the SAME PHP platform
#   assets -> npm ci && npm run build (Vite), isolated from the PHP image
#   prod   -> slim runtime: code + vendor/ + built assets, opcache tuned
#   nginx  -> nginx:1.30 carrying the built public/ directory
#   dev    -> base + Xdebug + Composer; code arrives via bind mount
#
# why: one Dockerfile with named targets keeps dev and prod images provably
# built from the same foundation, while docker-compose picks the target.
# =============================================================================

ARG PHP_VERSION=8.4
ARG NODE_VERSION=22
ARG NGINX_VERSION=1.30

# ---------------------------------------------------------------------------
# base: shared PHP-FPM foundation
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-alpine AS base

# why: UID/GID are build args so the container user matches the host user;
# otherwise files written through dev bind mounts end up owned by root.
ARG WWWUSER=1000
ARG WWWGROUP=1000

# Pinned helper that resolves each extension's build dependencies.
# Alternative (more verbose): apk add $PHPIZE_DEPS libpq-dev ... && docker-php-ext-install ...
ADD --chmod=0755 \
    https://github.com/mlocati/docker-php-extension-installer/releases/download/2.11.12/install-php-extensions \
    /usr/local/bin/install-php-extensions

# Only what Laravel 13 + PostgreSQL + image resizing need. pcntl is required
# by `queue:work --timeout` to kill stuck jobs; fcgi provides cgi-fcgi for the
# php-fpm healthcheck.
RUN install-php-extensions pdo_pgsql mbstring bcmath gd zip intl opcache pcntl \
    && apk add --no-cache fcgi

RUN addgroup -g ${WWWGROUP} app \
    && adduser -D -u ${WWWUSER} -G app app

WORKDIR /var/www/html

# why: php-fpm's master runs as non-root in this image, so the pool user/group
# directives would only produce warnings - remove them.
RUN sed -i '/^user = /d;/^group = /d' /usr/local/etc/php-fpm.d/www.conf

COPY docker/php/base.ini   /usr/local/etc/php/conf.d/zz-base.ini
COPY docker/php/zz-app.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY --chmod=0755 docker/php/healthcheck-fpm /usr/local/bin/healthcheck-fpm
COPY --chmod=0755 docker/entrypoint.sh      /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# vendor: production Composer dependencies
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app

# why: copying only the manifests first lets Docker cache the (slow) install
# layer until composer.json/lock actually change.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ---------------------------------------------------------------------------
# assets: frontend build (Vite)
# ---------------------------------------------------------------------------
FROM node:${NODE_VERSION}-alpine AS assets
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---------------------------------------------------------------------------
# prod: slim runtime image
# ---------------------------------------------------------------------------
FROM base AS prod
ENV APP_ENV=production

COPY docker/php/prod.ini /usr/local/etc/php/conf.d/zz-env.ini

COPY --chown=app:app . .
COPY --from=vendor --chown=app:app /app/vendor ./vendor
COPY --from=assets --chown=app:app /app/public/build ./public/build

# why: .dockerignore strips storage contents (logs, caches) out of the build
# context, so recreate the skeleton Laravel expects and hand it to the app user.
RUN mkdir -p storage/app/public storage/framework/cache/data \
             storage/framework/sessions storage/framework/testing \
             storage/framework/views storage/logs bootstrap/cache \
    && chown -R app:app storage bootstrap/cache

USER app

# Rebuild the package manifest for the pruned (no-dev) vendor directory.
RUN php artisan package:discover --ansi

EXPOSE 9000

# ---------------------------------------------------------------------------
# nginx: web server carrying the built public/ directory
# ---------------------------------------------------------------------------
FROM nginx:${NGINX_VERSION}-alpine AS nginx

COPY nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=prod /var/www/html/public /var/www/html/public

# why: `artisan storage:link` runs inside the app container and cannot create
# the symlink in this image, so bake it; the storage volume is mounted at the
# same path in both containers, which keeps the link valid.
RUN ln -sfn ../storage/app/public /var/www/html/public/storage

# ---------------------------------------------------------------------------
# dev: tooling image - the code arrives via bind mount from docker-compose
# ---------------------------------------------------------------------------
FROM base AS dev
ENV APP_ENV=local

# XDEBUG_MODE=off keeps it dormant until the developer flips it in .env.
RUN install-php-extensions xdebug

COPY docker/php/dev.ini /usr/local/etc/php/conf.d/zz-env.ini
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

USER app
EXPOSE 9000
