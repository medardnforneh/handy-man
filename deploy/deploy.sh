#!/usr/bin/env bash
# Deploy the current checkout (docs/11-deployment.md). Run on the box, from the repo root:
#
#   deploy/deploy.sh            # build, migrate, restart
#   deploy/deploy.sh --first    # also: seed roles + taxonomy, create the first superadmin
#
# The order is the one that keeps the site answering: new images are built while the old ones
# serve; the schema moves forward under `down` only if a migration needs it (Laravel's migrations
# are transactional on Postgres, so a failed one leaves the old schema); then everything restarts
# on the new image and the caches are re-warmed by the entrypoint.
set -euo pipefail

cd "$(dirname "$0")/.."
compose=(docker compose -f deploy/compose.yml --env-file deploy/.env)

if [[ ! -f deploy/.env ]]; then
  echo "deploy/.env is missing — copy deploy/.env.production.example and fill it in." >&2
  exit 1
fi

echo "== build"
"${compose[@]}" build --pull

echo "== data services"
"${compose[@]}" up -d postgres redis

echo "== migrate"
"${compose[@]}" run --rm --no-deps app php artisan migrate --force --no-interaction

if [[ "${1:-}" == "--first" ]]; then
  echo "== first run: roles + taxonomy"
  "${compose[@]}" run --rm --no-deps app php artisan db:seed --class=DatabaseSeeder --force --no-interaction
  echo "== first run: the first superadmin"
  read -r -p "  email: " email
  read -r -p "  phone (+237…): " phone
  "${compose[@]}" run --rm --no-deps app php artisan staff:bootstrap "$email" "$phone"
fi

echo "== restart on the new image"
"${compose[@]}" up -d --remove-orphans
# Horizon must be told, or it keeps running the old code until its workers cycle.
"${compose[@]}" exec -T horizon php artisan horizon:terminate || true

echo "== health"
sleep 5
"${compose[@]}" ps
curl -fsS "https://$(grep -E '^SITE_HOST=' deploy/.env | cut -d= -f2)/up" >/dev/null && echo "  site: up"
