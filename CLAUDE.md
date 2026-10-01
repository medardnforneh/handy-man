# CLAUDE.md — entry point and non-negotiable rules

> **Read this before writing code.** Then `docs/05-build-plan.md` (the phased task plan whose IDs
> appear in commit messages) and `docs/BUILD_STATE.md` (where the build actually is).
>
> **Provenance note (2026-09-30).** This file was **reconstructed** from the 116 places in the
> repository that cite it — `Money.php` cites "Non-negotiable rules #1, #2", the idempotency stack
> cites #3, `routes/api.php` cites #4, and so on. It had never been committed, so every
> contributor and every agent worked against rules they could not read. The numbering below is
> pinned to those existing citations and **must not be renumbered**. Rules #5 and #10 are cited
> nowhere in the repo and could not be recovered — they are marked as such; restore them from the
> original if you have it, and do not reuse those numbers for anything else.

## What this is

A two-sided service marketplace and collaboration platform for Cameroon. Engagements may be
**on-site, remote, or hybrid**, and the platform owns the whole lifecycle: discovery, quoting,
agreement, escrowed money, execution, follow-up.

## Stack

| Layer | Choice | Notes |
|---|---|---|
| API + public web + admin | Laravel 13, PHP 8.3 | `backend/`. Blade for SEO pages, Filament 5 for `/admin` |
| Database | PostgreSQL 16 + PostGIS | also `citext`, `pg_trgm`, `btree_gist`. **Never SQLite**, including in tests |
| Cache / queue / sessions | Redis + Horizon | |
| Realtime | Reverb (Pusher protocol) | **Ephemeral only — never the source of truth.** The REST refetch is authoritative on reconnect |
| App | Ionic 8 + Angular + Capacitor | `mobile/`, one codebase → PWA + Android + iOS |
| Money | integer minor units in Postgres, double-entry ledger | no float, anywhere, ever |

## Non-negotiable rules

**#1 — Amounts are always integer minor units.** `bigint` in the database, `int` in PHP, never a
float and never a decimal for anything that moves. The one representation lives in
`app/Support/Money.php`.

**#2 — Every amount carries an explicit ISO-4217 currency,** `char(3)` uppercase. Arithmetic
between currencies is forbidden and throws. The minor-unit scale per currency is documented in
exactly **one** place — `Money::SCALES` — so the choice is never silent. XAF is scale 0.

**#3 — Every mutating API request carries an `Idempotency-Key`.** Mobile networks here retry;
duplicate writes are the default failure, not the edge case. A replay returns the stored response
without re-executing. See `app/Http/Middleware/Idempotency.php` and the `idempotency_keys` table.
Exemptions are listed in `config/api.php` and must be justified there.

**#4 — The API is additive-only, forever.** Never remove a field. Never tighten an existing
validation rule. Never change an enum's meaning. New behaviour is a new field or a new endpoint.
Old app builds live on real phones for months.

**#5 — _(not cited anywhere in the repository; not recovered)._**

**#6 — PII minimisation is a default, not a feature.** A pre-engagement provider never sees a
customer's exact address or coordinates — quarter and city only. Outbox payloads carry ids, not
people. Logs carry no phone numbers. See `docs/04-security-and-trust.md` §4 (Law No. 2024/017).

**#7 — No SQL string interpolation of untrusted data.** Raw SQL is allowed where the query builder
cannot express PostGIS or full-text search, but every value is a bound parameter and every
interpolated fragment is a compile-time constant or comes from a fixed whitelist.

**#8 — A status never changes by editing a row.** Every lifecycle transition goes through its
state machine (`JobStateMachine`, `QuotationStateMachine`, …), which owns the full transition
matrix and throws on an illegal move. No controller, admin action, seeder or migration sets a
status directly.

**#9 — Agreed terms are immutable; a change is a new version.** A revised quotation is a new row
with `supersedes_id`, the old one marked `superseded` — never an edit. The database enforces this
with triggers, not only the application.

**#10 — _(not cited anywhere in the repository; not recovered)._**

**#11 — The server narrates the conversation.** Structured messages (a quote submitted, a
milestone approved, a warranty issued) are written by the Action that performed the transition, in
its own transaction. A client may only ever post `text` or `voice`; a client posting a structured
kind is rejected.

## Architecture conventions

- **Business logic lives in Actions** — `app/Domain/<Module>/Actions/<DoTheThing>.php`, one public
  `handle()`. Controllers are thin: resolve, authorize, delegate, return a Resource.
- **Authorization** is a Policy (`app/Domain/<Module>/Policies/`, bound explicitly in
  `AppServiceProvider::POLICIES` — domain policies are not auto-discoverable).
- **Capabilities are not roles.** What someone may do is derived from *facts* about them
  (`app/Domain/Access/`), keyed to the engagement mode and risk tier. Spatie roles exist for
  **staff and org-internal roles only** — never for "customer" or "provider".
- **Cross-aggregate side effects go through the transactional outbox** (`app/Support/Outbox.php`),
  never a direct call from inside a transaction. A rolled-back transaction publishes nothing.
- **Validation lives in a FormRequest**, `app/Http/Requests/Api/V1/`.
- **Responses are Resources**, `app/Http/Resources/Api/V1/`.
- **Never call an external service from inside a database transaction.** Network latency becomes
  lock-hold time, and a rollback can undo the record of a call that already happened.

## API conventions

- Versioned in the URL: `/api/v1`. See rule #4.
- Every request: `X-App-Version: MAJOR.MINOR.PATCH`, `X-Device-Id: <uuid>`.
- Errors: **RFC 7807 `application/problem+json`** via `app/Support/Problem.php` — a stable
  machine-readable `type` clients switch on (never localised), a human `detail` (localised), and a
  `trace_id` support can search in the logs.
- A precondition that is not met is **not a 403**. It is a 409 naming the missing fact and a deep
  link to resolve it inline (`PreconditionUnmetException`).
- Money in responses is **always** `{"amount_minor": 20000, "currency": "XAF"}` — never a
  pre-formatted string, never a float.
- Timestamps: ISO-8601 with offset. The client localises.
- Pagination: **cursor, not offset.** Offset pagination over a growing feed produces duplicates.
- `Accept-Language: fr|en` honoured for every user-facing string.

## The worked vertical slice (reference)

`Note` (build plan P0-05) is the canonical example every feature copies:

```
app/Domain/Reference/Actions/CreateNote.php     the Action
app/Domain/Reference/Policies/NotePolicy.php    the Policy
app/Http/Requests/Api/V1/Reference/…            the FormRequest
app/Http/Resources/Api/V1/NoteResource.php      the Resource
app/Http/Controllers/Api/V1/Reference/…         the thin controller
tests/Feature/Api/Reference/                    the tests
```

It is wired to idempotency and the outbox. Copy its shape, not its subject matter.

## Testing floor

- Pest, against **real Postgres + PostGIS**. SQLite has no PostGIS, no citext, no native enums and
  no deferred constraint triggers — testing on it tests a different application.
- Every state machine: the full transition matrix, illegal moves included.
- Every money flow: a balance assertion.
- Three named concurrency tests, non-negotiable:
  1. Parallel offer accepts → one engagement.
  2. Duplicate webhooks → one ledger transaction.
  3. Parallel payout requests → one payout.
- A factory for every model.

## Frontend rules

- **No literal colours** and **no hard-coded user-facing strings** — both are CI gates
  (`npm run lint:colors`, `npm run lint:strings`). Colours come from `tokens/tokens.json`; strings
  from `i18n/source/{fr,en}.json`, which must stay at parity.
- The API client is **generated** from `openapi/openapi.yaml`. Never hand-write or hand-edit a
  client model; CI fails on drift.
- Both themes must pass WCAG AA (`npm run check:contrast`), and every waiver is printed, never
  hidden.

## Working practice

- Commit messages name the build-plan task ID.
- `docs/BUILD_STATE.md` is the live tracker: update it in the same commit as the work.
- **"We built it" is not "we proved it."** A status marker is not evidence; a test, a CI gate or a
  decision record is. Check what *calls* a thing, not what declares it — `npm run check:uncalled`
  exists because a month of the tracker said "complete" while 43 of 91 endpoints were unreachable
  from the app.
- A screenshot finds a dead affordance and a grep does not: the markup for a live control and a
  dead one is identical.
