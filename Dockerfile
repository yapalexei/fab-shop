# Production image for Railway. One image serves every service (web, horizon,
# scheduler, reverb) - each Railway service just runs a different start
# command (see railway/*.json). Local dev uses Sail, not this file.

# ---- PHP base: nginx + php-fpm, with the extras this app needs -------------
FROM serversideup/php:8.4-fpm-nginx AS base
USER root
# gd: intervention/image (job photo thumbnails) + dompdf (quote PDFs); exif: spatie/medialibrary.
RUN install-php-extensions gd exif
USER www-data

# ---- Composer dependencies --------------------------------------------------
FROM base AS vendor
WORKDIR /var/www/html
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# ---- Frontend build ---------------------------------------------------------
FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
# tsconfig/vite resolve ziggy-js from vendor/.
COPY --from=vendor /var/www/html/vendor ./vendor
# VITE_* values are baked into the JS bundle at build time, so Railway must
# pass them as build args (any service variable declared as ARG is available).
ARG VITE_APP_NAME="Fab Shop"
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
RUN npm run build

# ---- Final image ------------------------------------------------------------
FROM base
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /var/www/html/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi
