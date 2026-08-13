# handy-man-project

A two-sided service marketplace and collaboration platform for Cameroon. Engagements may be
on-site, remote, or hybrid; the platform owns the whole lifecycle. See `CLAUDE.md` for the rules
and `docs/` for the design.

## Monorepo layout

Each project lives in its own dedicated folder:

| Folder | What | Stack |
|---|---|---|
| `backend/` | API + public web (Blade/SEO) + admin (Filament) | Laravel 13, PHP 8.3, PostgreSQL 16 + PostGIS, Redis |
| `mobile/` | The app (one codebase → PWA + Android + iOS) | Ionic 8 + Angular + Capacitor 6 *(not scaffolded yet)* |
| `docs/` | Design docs + ADRs + the live build tracker (`BUILD_STATE.md`) | Markdown |

Top level also holds `CLAUDE.md` (entry point + non-negotiable rules) and `.github/` (CI).

## Running the whole thing locally

```bash
npm run dev          # Postgres + migrations + API on :8100 + app on :4200
npm run dev:fresh    # the same, but resets the local database and reseeds the demo data
```

Then read **[`docs/LOCAL_TESTING.md`](docs/LOCAL_TESTING.md)** — how to sign in to each surface
(the app shows the OTP code on screen in local development), who the seeded people are, and what
to click for every feature. It also lists, honestly, the features the API can do that the app has
no screen for yet.

## Getting started (backend)

Prereqs: PHP 8.3, Composer, PostgreSQL 16 + PostGIS, Redis. See `docs/BUILD_STATE.md` for how
this dev machine is set up.

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer test      # Pest (real Postgres + PostGIS)
composer analyse   # PHPStan level 6
composer lint      # Pint
```

## Where to read next

- `CLAUDE.md` — non-negotiable rules; read before writing code.
- `docs/05-build-plan.md` — the phased task plan (task IDs used in commits).
- `docs/BUILD_STATE.md` — current progress and environment setup.
