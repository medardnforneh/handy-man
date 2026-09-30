#!/bin/bash
#
# Everything a fresh container needs to run this project's gates.
#
# Two callers, one implementation:
#   - .claude/hooks/session-start.sh  — the SessionStart hook, on every web session
#   - the cloud environment's setup script — paste `bash .claude/hooks/install-deps.sh`
#
# NEVER fails the caller. A session that will not start because a dependency mirror was slow is
# worse than a session that starts and says what is missing, so every step warns and carries on.
# `set -e` is deliberately absent for that reason.
#
# Idempotent throughout: safe on a warm container, and the container state is cached once this
# finishes, so the slow parts only pay once.

set -uo pipefail

ROOT="${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
cd "$ROOT" || exit 0

ok=()      # what a session can now do
missing=() # what it cannot, and why

note_env() {
  # Persist a variable for the session. No-op when run outside a hook (the setup script case).
  [ -n "${CLAUDE_ENV_FILE:-}" ] && echo "export $1=$2" >> "$CLAUDE_ENV_FILE"
  export "$1=$2"
}

# --- node: the app, and the Blade asset pipeline ------------------------------------------------
#
# `npm install` rather than `npm ci` on purpose: the container image is cached after this runs, and
# install reuses what is already there where ci deletes node_modules and starts over.
#
# The root package.json has NO dependencies — its scripts (i18n, tokens, the lints, the contrast
# and reachability checks) are plain node. So there is nothing to install for the gates that matter
# most, which is why they were the ones that worked before this script existed.

if [ -d mobile ]; then
  if (cd mobile && npm install --no-audit --no-fund --loglevel=error); then
    ok+=("mobile: npm run build, npm run test:ci, npm run api:generate")
  else
    missing+=("mobile npm install failed — the app build and its unit suite cannot run")
  fi
fi

if [ -d backend ] && [ -f backend/package.json ]; then
  if (cd backend && npm install --no-audit --no-fund --loglevel=error); then
    ok+=("backend: npm run build (Vite → the Blade/Filament assets)")
  else
    missing+=("backend npm install failed — the Vite asset build cannot run")
  fi
fi

# --- headless Chrome for the app's Karma suite --------------------------------------------------
#
# `npm run test:ci` uses ChromeHeadlessCI, which needs CHROME_BIN. Chromium is in the image but
# under a versioned directory, so glob it rather than pinning a number that a base-image bump
# would silently break. Without this the suite dies with a spawn ENOTDIR that looks like a test
# failure and is not.

if [ -z "${CHROME_BIN:-}" ]; then
  chrome="$(ls -d /opt/pw-browsers/chromium-*/chrome-linux/chrome 2>/dev/null | sort -V | tail -1)"
  [ -z "$chrome" ] && chrome="$(command -v chromium || command -v chromium-browser || true)"

  if [ -n "$chrome" ] && [ -x "$chrome" ]; then
    note_env CHROME_BIN "$chrome"
  else
    missing+=("no Chrome binary found — mobile npm run test:ci will not launch a browser")
  fi
fi

# --- Postgres 16 + PostGIS ----------------------------------------------------------------------
#
# The testing floor is explicit (CLAUDE.md, doc 05): Pest runs against real Postgres + PostGIS,
# never SQLite, because SQLite has no PostGIS, no citext, no native enums and no deferred
# constraint triggers — the suite has a test that asserts it is not on SQLite.
#
# The image ships a stopped `16/main` cluster on 5432 and no PostGIS. Both are fixable here, so
# the database half of the backend suite is ready even though the vendor half may not be (below).

if command -v pg_ctlcluster > /dev/null 2>&1; then
  if ! ls /usr/share/postgresql/*/extension/postgis.control > /dev/null 2>&1; then
    # Only the launchpad PPAs are blocked by the proxy; archive.ubuntu.com serves this.
    apt-get -qq update > /dev/null 2>&1
    DEBIAN_FRONTEND=noninteractive apt-get -qq install -y --no-install-recommends \
      postgresql-16-postgis-3 > /dev/null 2>&1 \
      || missing+=("PostGIS could not be installed — the backend suite cannot run (it needs real PostGIS)")
  fi

  pg_isready -q 2>/dev/null || pg_ctlcluster 16 main start > /dev/null 2>&1 || true

  if pg_isready -q 2>/dev/null; then
    # A password on the postgres role, because phpunit.xml connects over TCP as `postgres`.
    su postgres -c "psql -qtAX -c \"ALTER ROLE postgres WITH PASSWORD 'postgres'\"" > /dev/null 2>&1

    for db in handyman_test handyman; do
      su postgres -c "psql -qtAX -lt" 2>/dev/null | cut -d\| -f1 | grep -qw "$db" \
        || su postgres -c "createdb $db" > /dev/null 2>&1

      # The first migration enables these itself, but it needs them to be INSTALLABLE, and CI
      # creates them up front too. btree_gist is the no-double-booking EXCLUDE constraint (P2-09).
      for ext in postgis citext pg_trgm btree_gist; do
        su postgres -c "psql -qtAX -d $db -c 'CREATE EXTENSION IF NOT EXISTS $ext'" > /dev/null 2>&1
      done
    done

    # phpunit.xml pins 5433 (the founder's Windows box runs PG16 there because PG13 owns 5432).
    # Those <env> entries do not force, so a real environment variable wins — same as CI.
    note_env DB_CONNECTION pgsql
    note_env DB_HOST 127.0.0.1
    note_env DB_PORT 5432
    note_env DB_USERNAME postgres
    note_env DB_PASSWORD postgres

    if su postgres -c "psql -qtAX -d handyman_test -c 'SELECT postgis_version()'" > /dev/null 2>&1; then
      ok+=("postgres: 16 + PostGIS on 5432, handyman_test ready")
    else
      missing+=("Postgres is up but PostGIS is not installable — the backend suite cannot run")
    fi
  else
    missing+=("the Postgres cluster would not start — the backend suite cannot run")
  fi
fi

# --- PHP dependencies ---------------------------------------------------------------------------
#
# This is the one that does not work in a web session, and the reason is worth stating in full
# rather than leaving the next person to rediscover it over an afternoon.
#
# Composer resolves metadata from repo.packagist.org (reachable, 200) and then downloads each
# package from GitHub. This session's proxy gates GitHub to the repositories ATTACHED to the
# session, and every route to a third-party repo is refused:
#
#     api.github.com/repos/…/zipball/…   403  "GitHub access to this repository is not enabled"
#     codeload.github.com/…              403  same
#     github.com/…/archive/…             403  same
#     github.com/…/info/refs             401  realm="ccr-gitengine"  (so --prefer-source too)
#
# GITHUB_TOKEN is set to the literal sentinel `proxy-injected`, which Composer reads as a
# credential — which is why the failure reads as "Could not authenticate against github.com"
# rather than as a refusal. Clearing it does NOT help: the 403 is unconditional (tested). Nor does
# a warm cache — `--prefer-source` writes git mirrors INTO the cache rather than reading packages
# out of it, so a failed run leaves gigabytes of half-clones and no install. Non-GitHub Composer
# mirrors are refused at the tunnel (CONNECT 403), so there is no alternative dist source either.
# gitlab.com archives DO work, so this is GitHub specifically, not archives in general.
#
# To fix it: allow a full Composer mirror host in the cloud environment's Network access settings,
# then `composer config -g repos.packagist composer https://<mirror>/composer/`. Until then the
# PHP gates are CI's — which is where they run on every push anyway.

# Can this session download a third-party package from GitHub at all?
#
# One HEAD against a small public repo's archive — the exact path Composer uses for a dist. A 403
# is the session's GitHub gate, and knowing that up front is the difference between a one-second
# skip and a ten-minute failure. Any transport error is treated as "no" for the same reason: the
# cost of guessing wrong towards "yes" is the ten minutes.
github_archives_reachable() {
  local code
  code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 -L \
    'https://codeload.github.com/psr/log/legacy.zip/refs/tags/3.0.2' 2>/dev/null)"

  [ "$code" = "200" ]
}

# `[ -d vendor ]` is NOT the readiness test, which the first version of this script got wrong: a
# failed install leaves the directory behind full of half-unpacked packages, so it reported
# "vendor/ present" and every gate then died on a missing autoloader. Test for the autoloader and
# the binaries the gates actually invoke.
composer_ready() {
  [ -f backend/vendor/autoload.php ] \
    && [ -x backend/vendor/bin/pint ] \
    && [ -x backend/vendor/bin/phpstan ] \
    && [ -x backend/vendor/bin/pest ]
}

if [ -d backend ] && [ -f backend/composer.json ]; then
  if composer_ready; then
    ok+=("backend: composer lint / analyse / test")
  elif ! github_archives_reachable; then
    # PROBE FIRST, and this matters more than it looks. Attempting the install anyway takes TEN
    # MINUTES and writes 5.5 GB of half-finished git mirrors into the Composer cache before
    # failing — every session, synchronously, on a container with a fixed disk allowance. One HTTP
    # request answers the same question in under a second.
    missing+=("composer install: the proxy gates GitHub to this session's own repositories, so Pint, PHPStan and Pest cannot run here — they run in CI. Full diagnosis and the fix are in the comment above this block.")
  else
    # A husk from an earlier failure would make Composer's own "nothing to install" check lie too.
    [ -d backend/vendor ] && ! composer_ready && rm -rf backend/vendor

    (cd backend && composer install --no-interaction --prefer-dist --no-progress --no-scripts \
        --quiet > /dev/null 2>&1)

    if composer_ready; then
      (cd backend && composer run-script post-autoload-dump --quiet > /dev/null 2>&1) || true
      ok+=("backend: composer lint / analyse / test")
    else
      # Leave nothing behind that a later run, or a person, could mistake for a good install.
      rm -rf backend/vendor
      missing+=("composer install: the proxy gates GitHub to this session's own repositories, so Pint, PHPStan and Pest cannot run here — they run in CI. Full diagnosis and the fix are in the comment above this block.")
    fi
  fi
fi

# --- say what this session can and cannot do ----------------------------------------------------

echo
echo "handy-man · session ready"
for line in "${ok[@]:-}"; do [ -n "$line" ] && echo "  ✓ $line"; done
echo "  ✓ root: npm run verify:frontend, check:uncalled, i18n:build, tokens:build (no deps needed)"
for line in "${missing[@]:-}"; do [ -n "$line" ] && echo "  ✗ $line"; done
echo

exit 0
