# CLAUDE.md

Entry point for Claude Code. Read this before writing any code. Read `docs/` for detail.

## Monorepo layout

Each project has its own dedicated folder under the repo root:

- **`backend/`** — the Laravel app (API + Blade public/SEO + Filament admin). **All Laravel paths
  in this file (`app/Domain/…`, `app/Support/Money.php`, `app/Filament/`, `routes/`, etc.) are
  relative to `backend/`.** Run `composer`/`php artisan` from there.
- **`mobile/`** — the Ionic 8 + **Angular** + Capacitor app (PWA + Android + iOS). One codebase.
- **`docs/`** — design docs, ADRs (`docs/adr/`), and the live tracker `docs/BUILD_STATE.md`.

## What this is

A two-sided service marketplace **and** collaboration platform for Cameroon. Customers
discover, hire, collaborate with, pay and review service providers. Engagements may be
completed **entirely on-site, entirely remotely, or hybrid** — the platform owns the whole
lifecycle whether or not the parties ever meet.

Three structural facts drive the architecture:

1. **Both sides can be an individual or a company.** When a company provides a service it
   assigns its own staff to do the work and report. That makes this a marketplace *and* a
   field-service-management tool. → `docs/01`
2. **Work is not always physical.** `engagement_mode` ∈ {onsite, remote, hybrid}. Geography is
   optional. Address, dispatch, check-in and panic only exist for physical work. → `docs/06`
3. **The unit of work is an engagement workspace**, not a booking: negotiation, versioned
   quotations, milestones, chat-as-state-machine, deliverables, warranty. → `docs/06`

## Stack (pinned — do not silently upgrade)

| Layer | Choice | Notes |
|---|---|---|
| Runtime | PHP 8.3+ | Laravel 13 minimum. Watch: some Laravel 13.3+ patches pull Symfony 8 needing PHP 8.4. Pin `^13.0` and test. |
| Framework | Laravel 13 | Released 2026-03-17. |
| Admin | Filament 5 | Stable (5.6.x). **Admin only — web only, never mobile.** Behind a staff/`superadmin` role. |
| Public web | Blade + Alpine 3 + Tailwind | SEO surface. Minimal JS. `/`, `/services/*`, `/pro/*`, `/blog/*`. Must work without the app. |
| **The app** | **Ionic 8 + Capacitor 6** (Vue or Angular + TS) → PWA + Android + iOS | **One web codebase, wrapped native.** The builder's mastered stack. Flutter kept as documented alternative — see `docs/08`. |
| Realtime | Laravel Reverb | Ephemeral events broadcast; never the source of truth. |
| DB | PostgreSQL 16+ with **PostGIS** | Not MySQL. Geo is core — but optional per `engagement_mode`. |
| Cache/queue | Redis + Horizon | |
| Search | Postgres FTS first | Meilisearch later if proven necessary. No Elasticsearch. |
| API auth | Sanctum access tokens + custom rotating refresh tokens | See `docs/04`. |
| Contracts | OpenAPI 3.1 → TypeScript client, generated in CI | **Never hand-write a client model.** |
| Design tokens | `tokens.json` → Tailwind + Ionic CSS vars + Filament theme | One source. Semantic names only — see `docs/08`. |
| Storage | S3-compatible | Region matters legally — see `docs/04` §4. |
| Money | Own double-entry ledger + MoMo aggregator | **No Stripe.** See `docs/03`. |
| Comms | WhatsApp Business API primary, push, SMS, email | WhatsApp is the channel here. See `docs/07`. |
| i18n | FR + EN, both first-class; one shared string source → Laravel lang + app i18n (vue-i18n/ngx-translate) | Cameroon is bilingual. See `docs/09`. |
| Tests | Pest | |

**The stack is Laravel + web tech end to end.** Blade (public/SEO), Filament (admin, web
only), a Laravel API, and one Ionic + Capacitor app (HTML/CSS/TS) that ships as PWA + Android +
iOS from a single codebase. No new language — the builder masters this. Public discovery is
Blade because a WebView app can't be crawled; a customer must be able to find a provider and
request a quote in Blade without loading the app bundle. Guard that in review.

**Access model — read `docs/10` before writing any authorization.** One app, one identity. The
customer section and provider section are **both always visible to every logged-in user** — no
role gate on navigation, no "become a provider" step, no mode switch. You become a provider by
*using* the provider section. A small set of high-stakes actions gate on **verified facts**
(`identity_verified`, `has_payout_method`, `skill_listed`) — never on roles — checked inline at
the moment of the action. Spatie roles are only for org-internal roles (dispatcher, worker) and
staff/admin. Do not use roles to gate the section split.

## Non-negotiable rules

These are invariants. If a task seems to require breaking one, stop and ask.

1. **Money lives in the ledger, never in a balance column.** `ledger_entries` is append-only.
   No `UPDATE`, no `DELETE`, ever. A balance is a `SUM`. If you are tempted to write
   `$wallet->balance += $x`, you are doing it wrong.
2. **All amounts are integer minor units** (`bigint`, XAF centimes) plus an explicit
   `currency char(3)`. Never float. Never `decimal` for money movement.
   Note: XAF has no minor unit in practice — still store minor units at scale 0 and keep the
   column semantics uniform. Document the scale in one place, `app/Support/Money.php`.
3. **Every mutating API endpoint accepts `Idempotency-Key`.** Mobile networks here retry.
   Duplicate writes are the default failure, not the edge case.
4. **API is additive-only.** `/api/v1` from the first commit. Never remove a field, never
   tighten a validation rule, never change an enum's meaning. Old app builds live for months.
5. **Never trust client geo alone.** GPS is spoofable. Check-in = geo + server timestamp +
   photo, recorded together, and treated as evidence rather than proof.
6. **PII is minimised by default.** Exact address and phone are not exposed until an
   engagement exists. See `docs/04-security-and-trust.md`.
7. **No raw SQL string interpolation.** Ever. Especially in PostGIS queries.
8. **Every state transition goes through a state machine class**, not a controller setting
   `$job->status = 'x'`. Illegal transitions must throw.
9. **Never mutate a submitted quotation.** Revision = new version + `supersedes_id`. The
   version chain is the negotiation record. → `docs/06`
10. **Geography is conditional.** Never assume `jobs.address_id` is present. Branch on
    `engagement_mode` through a policy object, not scattered `if` statements. → `docs/06`
11. **The server narrates the chat.** Structured messages (`quote_accepted`, `arrived`, …) are
    emitted by the Action that performs the transition, in the same transaction, via the
    outbox. A client may never post one. → `docs/06`
12. **Follow-ups are scheduled on events and cancelled by events**, deduplicated by
    `dedupe_key`, and budgeted per user per channel. Never poll. → `docs/07`
13. **No hard-coded user-visible strings — FR and EN are both first-class.** Every UI string
    comes from the shared i18n source; every catalog entity has both languages; user-authored
    text is language-tagged. Never concatenate translated fragments. → `docs/09`
14. **Navigation is never role-gated; capabilities gate on verified facts.** Both sections are
    always visible to every user. Guarded actions check a fact (`identity_verified`, …) and
    return a structured `precondition_unmet` the app resolves inline. Never gate the section
    split by role. → `docs/10`
15. **The accept-paid-job gate keys on `engagement_mode`.** On-site/hybrid → full ID + safety
    apparatus; remote → lighter identity check, no home-visit tier. → `docs/10`, `docs/06`

## Architecture conventions

- **Domain modules** under `app/Domain/{Identity,Access,Catalog,Jobs,Offers,Quotations,
  Engagements,Workspace,Money,Trust,Reviews,Referrals,FollowUps,Notifications}`. `Access`
  holds the capability/guard objects from `docs/10`.
- Controllers are thin. They validate, call an **Action**, return a **Resource**.
- One public method per Action class: `handle()`. Actions are the unit of testing.
- Eloquent models hold relationships, casts, and scopes. No business logic.
- Anything that fans out (notifications, ledger side effects, webhooks) goes through the
  **transactional outbox** (`outbox_messages`), not a direct dispatch inside a DB transaction.
- Filament Resources live in `app/Filament/`, and are **admin-only**. Never expose Filament to
  customers or providers — they get Blade (public), the Ionic app, or the API.

### Resolved layout (P0-05) — where each layer lives

| Layer | Location | Rule |
|---|---|---|
| Eloquent model | `app/Models/{Model}.php` | relationships, casts, scopes **only** — no business logic |
| Factory | `database/factories/{Model}Factory.php` | one per model |
| Action | `app/Domain/{Module}/Actions/{Verb}{Noun}.php` | one public `handle()`; wraps writes in a transaction; fans out via the outbox |
| Policy | `app/Domain/{Module}/Policies/{Model}Policy.php` | registered in `AppServiceProvider::POLICIES` (domain policies aren't auto-discovered) |
| FormRequest | `app/Http/Requests/Api/V1/{...}Request.php` | all validation; rules are additive-only |
| API Resource | `app/Http/Resources/Api/V1/{...}Resource.php` | the only place a response shape is defined |
| Controller | `app/Http/Controllers/Api/V1/{...}Controller.php` | thin: validate → authorize → call Action → return Resource |

Cross-cutting infra lives in `app/Support/` (`Money`, `Problem`, `Outbox`, `OutboxRelay`) and
`app/Http/Middleware/` (`EnforceAppVersion`, `Idempotency`). Errors are always RFC 7807
`application/problem+json` — build them with `App\Support\Problem::make()`, never ad-hoc.

**The worked vertical slice (reference).** The `Note` feature is the canonical example, not a
product feature: `App\Domain\Reference\Actions\CreateNote`, `…\Policies\NotePolicy`,
`App\Http\Controllers\Api\V1\Reference\NoteController`, `NoteResource`, `StoreNoteRequest`,
`App\Models\Note`, `tests/Feature/Api/Reference/NoteSliceTest.php`. Copy its shape for Phase 1+
features. (It uses `auth`; P1-03 swaps that for `auth:sanctum`.)

## Naming

- Tables: plural snake_case. Enums: Postgres native enum types, named `{noun}_{attr}` e.g.
  `job_status`, `offer_origin`.
- Money columns: `*_minor` (bigint) always paired with a `currency` column.
- Geo columns: `geography(Point,4326)`, named `point` / `center`.
- Timestamps: `*_at`. Booleans: `is_*` / `has_*`, but prefer a nullable `*_at` over a boolean
  when the moment matters (`verified_at`, not `is_verified`).

## Definition of done, per task

- Migration + model + factory + Action + Policy + Pest feature test + OpenAPI entry.
- Postgres constraints, not just app validation. If it must be true, it's a `CHECK`, a
  `UNIQUE`, or an `EXCLUDE`. App validation is a UX nicety, the DB is the guarantee.
- No task is done if it adds a way for money or state to become inconsistent under concurrency.

## Read next

- `docs/01-product-and-strategy.md` — what we're building; the decisions that constrain everything
- `docs/02-domain-model.md` — the base schema
- `docs/03-money-and-ledger.md` — the ledger, MoMo, escrow
- `docs/04-security-and-trust.md` — two-sided safety, auth, Cameroon data law
- `docs/05-build-plan.md` — phased tasks with acceptance criteria
- `docs/06-engagement-workspace.md` — **supersedes doc 02's thin offer→engagement flow**;
  modes, quotations, milestones, warranty, chat-as-state-machine
- `docs/07-follow-ups-and-lifecycle.md` — scheduled client follow-ups, WhatsApp, comms budget
- `docs/08-frontend-architecture.md` — frontend decision record; **Ionic + Capacitor** chosen
  (builder's stack), Flutter kept as the documented alternative with a switch trigger
- `docs/09-internationalization.md` — FR/EN across all surfaces, the data layer, and consent
- `docs/10-access-model.md` — **open navigation, fact-gated actions.** Read before any auth code

**Where docs conflict, the higher number wins.** Doc 06 supersedes doc 02 on the engagement
lifecycle. Doc 08 (Ionic + Capacitor) supersedes any earlier mention of Flutter, React, or
Livewire for the app. Doc 10 supersedes any earlier role-gated or mode-switch access model.
