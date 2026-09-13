#!/bin/sh
# Entrypoint for every PHP role (fpm, horizon, outbox, reverb, scheduler).
#
# Warms Laravel's caches from the environment the container was started with. `config:cache` is
# what makes `.env` unnecessary in the image — every setting comes from the compose environment —
# and it MUST run at start rather than at build, because the values are not known at build time.
# Idempotent and fast, so every role does it; migrations are deliberately not here (see
# deploy/deploy.sh — a schema change is a decision, not a side-effect of a restart).
set -e

cd /var/www/html

php artisan config:cache --no-ansi >/dev/null
php artisan route:cache --no-ansi >/dev/null
php artisan view:cache --no-ansi >/dev/null
php artisan event:cache --no-ansi >/dev/null

# The public storage link (job photos served through the web image's public/ mount).
if [ ! -e public/storage ]; then
  php artisan storage:link --no-ansi >/dev/null || true
fi

exec "$@"
