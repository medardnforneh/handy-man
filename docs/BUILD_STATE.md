# Build State

> Living tracker for the build. Updated as work progresses. Source of truth for **where we
> are** and **how this machine is set up**. Read this first when resuming.

_Last updated: 2026-07-18_

## Environment (this dev machine — Windows 10 Pro, non-admin)

| Tool | Version | How installed | Notes |
|---|---|---|---|
| Node | 22.14.0 | pre-existing | for Ionic app + Vite |
| PHP | 8.3.32 | Scoop (`versions/php83`, set default) | 8.5 also present but 8.3 is pinned per CLAUDE.md |
| Composer | 2.10.2 | Scoop | |
| PostgreSQL | 16.x | Scoop (`versions/postgresql16`) | **portable**, runs on **port 5433** (existing PG13 owns 5432) |
| PostGIS | TBD | manual Windows bundle | not a Scoop package — added by hand |
| Git | 2.45 | pre-existing | repo initialised |

**Scoop shims are at `%USERPROFILE%\scoop\shims`.** Fresh shells sometimes don't have it on
PATH yet — prefix commands with:
`$env:PATH = "$env:USERPROFILE\scoop\shims;$env:PATH"` (PowerShell).

**PHP ini**: `%USERPROFILE%\scoop\apps\php83\current\php.ini` — enabled: pdo_pgsql, pgsql,
mbstring, openssl, curl, fileinfo, zip, intl, sodium, gd (bcmath is built in).

## Repo layout decisions (monorepo — each project in its own folder)

- **`backend/`** — the Laravel app (API + Blade + Filament). Run composer/artisan from here.
  CLAUDE.md's Laravel paths are relative to this folder.
- **`mobile/`** — Ionic 8 + **Angular** + Capacitor app (PWA/Android/iOS). Not scaffolded yet.
- **`docs/`** — design docs, `docs/adr/`, and this tracker. Stays at repo root.
- Root also holds `CLAUDE.md`, `README.md`, `.github/` (CI runs with `working-directory: backend`).
- History: the Laravel app started at the repo root, then moved into `backend/` on 2026-07-18
  per the "each project in its own folder" directive.

## Progress by phase

Task IDs come from `docs/05-build-plan.md`.

### Phase 0 — Foundations

| ID | Task | Status |
|---|---|---|
| P0-01 | Laravel 13 skeleton + Pest/Pint/PHPStan L6 + CI | **DONE** — Laravel 13.20; `composer lint`/`analyse`/`test` all green; `.github/workflows/ci.yml` (PHP 8.3 + postgis service) |
| P0-02 | Postgres 16 + PostGIS + citext + pg_trgm | **DONE** — PG16 on :5433, PostGIS 3.6 + citext + pg_trgm; enabled via first migration; capability test passes in suite |
| P0-03 | Redis + Horizon | **DONE** — predis client; cache+queue on Redis; Horizon 5.47 installed, dashboard deny-by-default (staff allowlist, `HORIZON_DASHBOARD_EMAILS`); Redis service added to CI; 4 tests. (Windows: `horizon` supervisor needs pcntl/posix — runs on Linux prod; `config.platform` fakes them for install) |
| P0-04 | `app/Support/Money.php` | **DONE** — immutable Money VO (integer minor units, currency guard, sign convention + XAF scale-0 documented, allocate/percentage exact-split); 14 unit tests |
| P0-05 | Action / Resource / Policy conventions + vertical slice | **DONE** — resolved layout documented in CLAUDE.md; `Note` reference slice (Action+Policy+Request+Resource+thin controller) wired to idempotency+outbox; 6 feature tests |
| P0-06 | Idempotency middleware + `idempotency_keys` | **DONE** — global api middleware; atomic claim via INSERT ON CONFLICT; replay stored response; reuse/conflict handling; 5 tests |
| P0-07 | Transactional outbox + relay worker | **DONE** — `outbox_messages`, `Outbox` publisher, `OutboxRelay` (FOR UPDATE SKIP LOCKED), `outbox:relay` command; rolled-back txn publishes nothing; 5 tests |
| P0-08 | `/api/v1` scaffold + version header + force-update | **DONE** — `/api/v1`, `EnforceAppVersion` (426), RFC7807 problem+json for validation/auth/authz/http/404; 5 tests |
| P0-09 | Hosting region ADR (BLOCKER) | **ADR drafted** (`docs/adr/0001-hosting-region.md`, status PROPOSED) — awaiting founder+lawyer decision. Recommendation: start CNDP authorisation now; provisionally EU/OVHcloud unless in-country viable |
| P0-10 | OpenAPI 3.1 + CI codegen → TS client | **DONE** — spec-first `openapi/openapi.yaml` (3.1); `openapi-typescript` generates `mobile/src/app/api/generated/schema.d.ts`; typed `openapi-fetch` client; app builds against it; CI drift gate (hand-edited client fails CI) |
| P0-11 | `tokens.json` → Tailwind + Ionic + Filament | **DONE** — `tokens/tokens.json` (semantic, light+dark) → `tokens/build.mjs` emits CSS vars + Ionic map + Tailwind preset to backend + mobile; CI drift check |
| P0-12 | Blade scaffold + Ionic shell + Reverb | **DONE** — Ionic 8 + **Angular** app in `mobile/` (builds `www`; ngx-translate + tokens + Capacitor); crawlable token/i18n Blade landing page; Reverb + participant-gated channels (`ChannelAccess`); 4 web + 3 channel tests |
| P0-13 | Dark + light themes from tokens | **DONE** — `tokens.css` light/dark (system-follow + `[data-theme]` override), consumed by Ionic + Blade |
| P0-14 | Lint: no literal colours | **DONE** — `tools/lint-no-literal-colors.mjs`; catches hex/rgb/hsl in components (verified) |
| P0-15 | i18n scaffold FR/EN + missing-key gate | **DONE** — `i18n/source/{fr,en}.json` → Laravel flat + Angular nested; parity gate fails on missing key (verified); `SetLocale` + app `LocaleService` |
| P0-16 | Lint: no hard-coded user strings | **DONE** — `tools/lint-no-bare-strings.mjs`; strips tags/interpolation/translation calls, flags remainder (verified) |
| P0-17 | Access-model foundation (doc 10) | **DONE** — `Fact` enum, cached `FactDeriver` (pluggable resolvers, `forget()` invalidation), `Capability` base, `AcceptPaidJob` (tier keyed to engagement_mode), `PreconditionUnmetException` → problem+json 409 with `missing_fact`+`resolve` (not 403); 5 tests |
| P0-18 | Spatie scoped to org-internal + staff only | **DONE** — spatie/laravel-permission (teams on for org-scoped roles), `HasRoles` on User, `Role` enum (org+staff only, no customer/provider), `StaffRolesSeeder`; tests prove capabilities never consult roles & section split isn't role-gated |

Phases 1–8: not started (see build plan).

## What was done, most recent first

- **Frontend + i18n batch (P0-11/12/13/14/15/16 done)**: Ionic 8 + Angular app scaffolded in
  `mobile/` (builds; ngx-translate + Capacitor + tokens wired); shared `i18n/source` → Laravel +
  Angular with a parity gate; `tokens/tokens.json` → CSS vars/Ionic/Tailwind across surfaces
  (light+dark); no-literal-colour and no-bare-string lints; crawlable token/i18n Blade landing
  page with `SetLocale`; Reverb with participant-gated channels. Backend 61 tests green; frontend
  lints + Angular build green; frontend CI job added.
- **P0-18 done**: Spatie roles scoped to org-internal (team-scoped) + staff/admin (global) only;
  `Role` enum forbids a customer/provider role; tests prove the section split is fact-gated, never
  role-gated.
- **Monorepo restructure**: moved the Laravel app from repo root into **`backend/`** (per "each
  project in its own folder"); `mobile/` reserved for the Ionic Angular app. Updated CI
  (`working-directory: backend`), root `.gitignore` + `README.md`, CLAUDE.md layout note. 49
  tests still green from `backend/`.
- **P0-03 done**: Redis (predis) for cache+queue; Horizon dashboard protected deny-by-default.
- **P0-17 done**: access-model foundation per doc 10 — fact-gated capabilities, not roles. Unmet
  fact returns structured `precondition_unmet` (409, `missing_fact` + `resolve` deep link), never
  a 403. `AcceptPaidJob` keys the required identity tier on `engagement_mode`. Facts cached +
  invalidated via `forget()`. Real resolvers plug in during P1/P6 (`AccessServiceProvider`).
- **P0-05 / 06 / 07 / 08 done** (one batch): API foundation. `/api/v1` with force-update gate and
  RFC7807 errors; idempotency middleware; transactional outbox + relay; and the `Note` reference
  vertical slice tying Action/Policy/Request/Resource/thin-controller to that infra. 40 tests
  green overall; analyse + lint clean.
- **P0-09**: hosting-region ADR drafted (`docs/adr/0001-hosting-region.md`), awaiting decision.
- **P0-04 done**: `app/Support/Money.php` — immutable value object; integer minor units only,
  explicit currency with scale registry (XAF/XOF scale 0), cross-currency arithmetic throws,
  `allocate()` conserves minor units, `percentage()` round-half-up, `toArray()` = API shape.
  14 Pest unit tests.
- **P0-02 done**: PG16 running on :5433; PostGIS 3.6.2 Windows bundle merged into the Scoop PG
  install; `handyman` + `handyman_test` DBs created with postgis/citext/pg_trgm; extensions
  also enabled via `0000_00_00_000000_enable_postgres_extensions` migration; `phpunit.xml` +
  `.env` + `.env.example` point at pgsql; `DatabaseCapabilitiesTest` proves PostGIS in-suite.
- **P0-01 done**: Pest wired with `RefreshDatabase` on Feature suite; `phpstan.neon` L6;
  composer `lint`/`analyse`/`format`/`test` scripts; `.github/workflows/ci.yml`. All green.
- Scaffolded Laravel 13.20 at repo root; merged skeleton preserving CLAUDE.md + docs/.
- Removed `laravel/pao` (conflicts with Pest 4); installed Pest 4, pest-plugin-laravel 4.1,
  Larastan 3. Pinned `laravel/framework` to `^13.0`.
- Added `phpstan.neon` (level 6) and composer scripts: `test`, `analyse`, `lint`, `format`.
  Fixed UserFactory return type to `array<model-property<\App\Models\User>, mixed>`.
- git init; created this state file.

## Next steps

Phase 0 is essentially complete. Remaining:
1. **P0-10** — OpenAPI 3.1 spec + CI codegen → TypeScript client for the app. Needs a tool
   choice (code-first annotations vs spec-first `openapi.yaml`). Last Phase-0 item.
2. **P0-09** — hosting-region decision (ADR drafted; awaiting founder + lawyer).
3. Then **Phase 1** (identity + catalog): parties/users/orgs, OTP auth, Sanctum + refresh
   tokens, addresses+PostGIS, skills taxonomy, provider profiles, Filament panel, DSAR/erasure.

Follow-ups noted in code (not blocking): wire real fact resolvers in `AccessServiceProvider`
(P1/P6); `auth` → `auth:sanctum` on API routes (P1-03); `cap add android/ios` when building
native; full Tailwind/Vite pipeline for Blade (currently token CSS is linked directly).

## Open decisions / to confirm with user

- P0-09 hosting region is a real decision (Cameroon Law 2024/017) — needs input, not a guess.
- **Ionic flavour: DECIDED → Angular + TypeScript** (ngx-translate for i18n).
- Redis: running as a Windows service on :6379 (confirmed). Using **predis** client (no phpredis
  extension). Note: `php artisan horizon` needs pcntl (not on Windows) — dashboard + config work
  locally; the Horizon supervisor runs on Linux in prod/CI. Local queues: `queue:work redis`.
