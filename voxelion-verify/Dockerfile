# Voxelion Verify — all-in-one image for Render (no native PHP runtime exists,
# so the app is packaged with Docker).
#
#   docker build -t voxelion-verify .
#   docker run -p 8000:8000 voxelion-verify

# ---- Stage 1: build frontend assets (Vite + Tailwind) ----
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---- Stage 2: install Composer dependencies ----
FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# ---- Stage 3: runtime ----
FROM php:8.2-cli

# pdo_pgsql: Render Postgres. intl: IDN → punycode email normalisation.
# (pdo_sqlite/mbstring/curl are already compiled into the official image.)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libicu-dev libzip-dev unzip \
    && docker-php-ext-install pdo_pgsql intl zip bcmath \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

RUN mkdir -p storage/app/keys bootstrap/cache \
    && touch database/database.sqlite \
    && chmod -R 777 storage bootstrap/cache database/database.sqlite

EXPOSE 8000

# Boot: generate dev RSA keypair if absent (ephemeral FS — see DEPLOYMENT.md),
# run migrations, warm config, serve on Render's $PORT.
CMD ["sh", "-c", "\
    php artisan voxelion:keys || true; \
    php artisan migrate --force; \
    php artisan config:cache; \
    php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]
