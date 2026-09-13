# syntax=docker/dockerfile:1.7
#
# The production images (docs/11-deployment.md). Build from the REPO ROOT:
#
#   docker build -f deploy/Dockerfile --target app -t handyman-app .
#   docker build -f deploy/Dockerfile --target web -t handyman-web .
#
# Two images from one file, so the PHP code Caddy points at and the PHP code FPM runs are the same
# bytes by construction:
#   app  — PHP-FPM with the Laravel code and vendor. Also the image every long-lived role runs
#          (horizon, outbox relay, reverb, scheduler) with a different command.
#   web  — Caddy, with the site's built assets, the storage link's public files and the PWA. It
#          serves static files itself and hands PHP to `app` over FastCGI.
#
# Three build stages feed them: composer (vendor, no dev), node (Vite assets for the site and the
# production PWA bundle), and the generated design tokens the site's CSS imports.

# --- assets: tokens → Vite CSS for the site, and the PWA -----------------------------------------
FROM node:22-alpine AS assets
WORKDIR /src
# The token generator and i18n build write into backend/ and mobile/, so they need the whole tree.
# They are plain Node with no dependencies — the repo root has no lockfile and needs no install.
COPY tokens ./tokens
COPY i18n ./i18n
COPY tools ./tools
COPY openapi ./openapi
COPY backend ./backend
COPY mobile ./mobile
RUN node tokens/build.mjs \
 && node i18n/build.mjs
# The site's CSS (Tailwind v4 through Vite). Blade views are scanned for class names, so they
# must be present — they are, from the COPY above.
RUN cd backend && npm ci --ignore-scripts && npm run build
# The PWA, production configuration: same-origin /api/v1, Reverb on 443.
RUN cd mobile && npm ci --ignore-scripts && npx ng build --configuration production

# --- vendor: Composer, production only --------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
# The lock was resolved on Windows with pcntl/posix faked (docs/BUILD_STATE.md); the real
# extensions are in the runtime image, so nothing needs faking here.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY backend ./
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# --- app: PHP-FPM -------------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS app
# Same extension list as the dev machine's php.ini (docs/BUILD_STATE.md) plus what the servers
# need: pcntl/posix for Horizon and Reverb, opcache for speed, sockets for Reverb.
RUN apk add --no-cache icu-libs libpq libzip libpng libjpeg-turbo freetype libsodium \
 && apk add --no-cache --virtual .build icu-dev postgresql-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev libsodium-dev linux-headers $PHPIZE_DEPS \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql intl zip gd bcmath opcache pcntl posix sockets sodium \
 && apk del .build
COPY deploy/php/php.ini /usr/local/etc/php/conf.d/99-handyman.ini
COPY deploy/php/www.conf /usr/local/etc/php-fpm.d/zz-handyman.conf
WORKDIR /var/www/html
COPY --from=vendor /app ./
COPY --from=assets /src/backend/public/build ./public/build
COPY --from=assets /src/backend/public/css ./public/css
COPY --from=assets /src/backend/config/tokens.php ./config/tokens.php
COPY --from=assets /src/backend/lang ./lang
# Writable at runtime: storage/ (uploads, logs, cache) is a volume; bootstrap/cache holds the
# optimised config/routes written at container start (deploy/php/entrypoint.sh).
RUN chown -R www-data:www-data storage bootstrap/cache
COPY deploy/php/entrypoint.sh /usr/local/bin/handyman-entrypoint
RUN chmod +x /usr/local/bin/handyman-entrypoint
USER www-data
ENTRYPOINT ["handyman-entrypoint"]
CMD ["php-fpm"]

# --- web: Caddy ---------------------------------------------------------------------------------
FROM caddy:2-alpine AS web
COPY deploy/Caddyfile /etc/caddy/Caddyfile
# The site's static files, at the path FPM sees them at, so php_fastcgi's SCRIPT_FILENAME lines up.
COPY --from=app /var/www/html/public /var/www/html/public
# The PWA, served from its own hostname with an SPA fallback.
COPY --from=assets /src/mobile/www /srv/app
