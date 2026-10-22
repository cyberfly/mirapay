# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1 — PHP dependencies
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# Scripts are skipped here because the application source is not present yet;
# package discovery runs in the app stage once the full app is copied in.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --ignore-platform-req=ext-pcntl

# ---------------------------------------------------------------------------
# Stage 2 — front-end assets (Vite + Tailwind)
#
# resources/css/app.css imports Flux's stylesheet and scans the Flux blade
# stubs, so the Composer vendor tree has to be in place before Vite runs.
# Debian (glibc), not Alpine: the native optional deps are linux-x64-gnu.
# ---------------------------------------------------------------------------
FROM node:24-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json .npmrc ./
RUN npm ci --include=optional

COPY --from=vendor /app/vendor ./vendor
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build

# ---------------------------------------------------------------------------
# Stage 3 — application runtime (PHP-FPM)
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-bookworm AS app

ENV APP_ENV=production \
    APP_DEBUG=false \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        default-mysql-client \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-app.conf

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

# The storage tree is excluded from the build context (see .dockerignore) and
# is replaced by a bind mount at runtime, but artisan needs it to exist now.
RUN rm -f public/hot \
    && mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R ug+rwX storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --retries=5 --start-period=60s \
    CMD php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);'

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# Stage 4 — web server (nginx)
#
# nginx only ever serves the built public/ directory and proxies everything
# else to PHP-FPM, so it carries the static assets and none of the PHP source.
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS web

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

COPY --from=app /var/www/html/public /var/www/html/public

RUN mkdir -p /var/www/html/storage/app/public

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --retries=5 --start-period=30s \
    CMD wget --spider -q http://127.0.0.1/up || exit 1
