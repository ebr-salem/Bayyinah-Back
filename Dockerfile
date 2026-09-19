# syntax=docker/dockerfile:1

# Base build args shared across stages
ARG PHP_VERSION=8.4
ARG NODE_VERSION=22

# ---------------------------------------------------------------
# Stage 1: Composer dependencies
# ---------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# Install dependencies without scripts/autoloader first (fast, cached layer)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --no-interaction \
    --no-progress \
    --prefer-dist

COPY . .

# Generate optimized autoloader and run post-install scripts (package:discover)
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader

# ---------------------------------------------------------------
# Stage 2: Frontend assets (Vite / Tailwind)
# ---------------------------------------------------------------
FROM node:${NODE_VERSION}-alpine AS assets

WORKDIR /app

COPY package.json .npmrc ./

RUN npm install --ignore-scripts --no-audit --no-fund

COPY . .

RUN npm run build

# ---------------------------------------------------------------
# Stage 3: Runtime — PHP-FPM + Nginx
# ---------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-bookworm AS runtime

ARG DEBIAN_FRONTEND=noninteractive

# System packages + PHP extensions
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        nginx \
        poppler-utils \
        libcurl4-openssl-dev \
        libssl-dev \
        zlib1g-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql opcache curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# PHP configuration
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Nginx configuration (runs inside the same container)
COPY nginx/default.conf /etc/nginx/conf.d/default.conf

WORKDIR /var/www/html

# Application code + production dependencies + built assets
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

# Storage bootstrap
RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && rm -rf /etc/nginx/sites-enabled/default

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=45s --retries=3 \
    CMD curl -fsS http://127.0.0.1/ > /dev/null || exit 1

ENTRYPOINT ["entrypoint.sh"]