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
| P0-09 | Hosting region ADR (BLOCKER) | **DECIDED — Option A: in-country (Cameroon)** (`docs/adr/0001-hosting-region.md`, ACCEPTED, lawyer sign-off pending). No cross-border transfer → cleanest compliance; self-manage PostgreSQL+PostGIS/Redis/MinIO in-country; keep deployment provider-agnostic. CNDP processing register still a founder task |
| P0-10 | OpenAPI 3.1 + CI codegen → TS client | **DONE** — spec-first `openapi/openapi.yaml` (3.1); `openapi-typescript` generates `mobile/src/app/api/generated/schema.d.ts`; typed `openapi-fetch` client; app builds against it; CI drift gate (hand-edited client fails CI) |
| P0-11 | `tokens.json` → Tailwind + Ionic + Filament | **DONE** — `tokens/tokens.json` (semantic, light+dark) → `tokens/build.mjs` emits CSS vars + Ionic map + Tailwind preset to backend + mobile; CI drift check |
| P0-12 | Blade scaffold + Ionic shell + Reverb | **DONE** — Ionic 8 + **Angular** app in `mobile/` (builds `www`; ngx-translate + tokens + Capacitor); crawlable token/i18n Blade landing page; Reverb + participant-gated channels (`ChannelAccess`); 4 web + 3 channel tests |
| P0-13 | Dark + light themes from tokens | **DONE** — `tokens.css` light/dark (system-follow + `[data-theme]` override), consumed by Ionic + Blade |
| P0-14 | Lint: no literal colours | **DONE** — `tools/lint-no-literal-colors.mjs`; catches hex/rgb/hsl in components (verified) |
| P0-15 | i18n scaffold FR/EN + missing-key gate | **DONE** — `i18n/source/{fr,en}.json` → Laravel flat + Angular nested; parity gate fails on missing key (verified); `SetLocale` + app `LocaleService` |
| P0-16 | Lint: no hard-coded user strings | **DONE** — `tools/lint-no-bare-strings.mjs`; strips tags/interpolation/translation calls, flags remainder (verified) |
| P0-17 | Access-model foundation (doc 10) | **DONE** — `Fact` enum, cached `FactDeriver` (pluggable resolvers, `forget()` invalidation), `Capability` base, `AcceptPaidJob` (tier keyed to engagement_mode), `PreconditionUnmetException` → problem+json 409 with `missing_fact`+`resolve` (not 403); 5 tests |
| P0-18 | Spatie scoped to org-internal + staff only | **DONE** — spatie/laravel-permission (teams on for org-scoped roles), `HasRoles` on User, `Role` enum (org+staff only, no customer/provider), `StaffRolesSeeder`; tests prove capabilities never consult roles & section split isn't role-gated |

### Phase 1 — Identity and catalog

| ID | Task | Status |
|---|---|---|
| P1-01 | parties/users/orgs/memberships + kind trigger | **DONE** — UUID identity per doc 02 (reconciled P0 bigint→UUID: Spatie morph/team keys, notes, idempotency, sessions all UUID); native enums `party_kind`/`user_status`/`membership_role`; constraint trigger rejects user↔org-kind mismatch at DB; phone-primary, OTP-first (password optional); 7 tests. `TestCase::$dropTypes` so RefreshDatabase drops enum types |
| P1-02 | OTP signup/login + rate limits | **DONE** — `otp_challenges` (hashed codes), `RequestOtp`/`VerifyOtp` actions, phone 3/hr + IP 10/hr + device 5/hr limits, hard-lock after 5 wrong verifies (attempt increment persists outside txn), find-or-create user on first verify; `POST /v1/auth/otp/{request,verify}`; 6 tests incl. 4th-request-rejected. Also fixed pgsql session **timezone→UTC** (timestamptz was shifting by +1h, breaking tight windows) |
| P1-03 | Sanctum access + rotating refresh tokens + reuse detection | **DONE** — Sanctum 15m access tokens (uuid tokenable); `refresh_tokens` (opaque 256-bit, sha256-hashed, 30d, family rotation); `IssueAuthTokens`/`RotateRefreshToken`; **replaying a rotated token revokes the whole family + wipes access tokens** (revocation persists outside txn); tokens issued on OTP verify; `POST /v1/auth/refresh`, `GET /me`, `POST /logout`; reference routes → `auth:sanctum`; `ProblemAware` interface unifies error rendering; 7 tests |
| P1-04 | devices registration + push token capture | **DONE** — `devices` (id == client X-Device-Id, upsert), `RegisterDevice` action (moves a push token to the newest device), `POST /v1/devices` (auth:sanctum); captures platform/push_token/app_version; 5 tests |
| P1-05 | consents (granular/versioned/revocable + presented_locale) | **DONE** — append-only `consents` log (terms/privacy/location_tracking/id_verification/marketing), policy_version, presented_locale (CHECK fr/en); `ConsentState` (latest-per-purpose), `ConsentGuard` blocks geo writes when location_tracking revoked → `consent_required` problem+json (`missing_purpose`); `GET/POST /v1/consents`; `ProvidesProblemExtras` interface |
| P1-05b | locale + comms_locale prefs | **DONE** — columns already on users (P1-01); `PATCH /v1/me/preferences` sets UI + comms locale independently (locale=en, comms_locale=fr round-trips); app-side first-launch offer via `LocaleService` (P0-12) |
| P1-06 | addresses + PostGIS + GIST index | **DONE** — `addresses` with `geography(Point,4326)` + GIST spatial index (via matanyadaev/laravel-eloquent-spatial); `Address::scopeNear` uses **ST_DWithin** (index-served, parameterized); `CreateAddress` gated on location_tracking consent; `GET/POST /v1/addresses`; 5 tests (proximity correctness + index existence). 100k/<50ms benchmark left to a perf script |
| P1-07 + P1-07b | bilingual skills taxonomy + language-matched FTS | **DONE** — self-referencing `skills` (name_fr/name_en, slug citext, risk_tier 1–3, requires_license); `SkillsSeeder` = 41 leaves / 13 categories real Cameroon trades, both languages; `Skill::scopeSearch` uses the **matching french/english FTS config** (GIN indexes per language); public `GET /v1/skills` + `/skills/search`; `DatabaseSeeder` runs staff roles + skills; 6 tests |
| P1-08 | provider_profiles + provider_skills + service_areas | **DONE** — 3 tables (service_areas geography+GIST; provider_skills price_model enum); `CreateProviderProfile` (always allowed, doc 10), `AddProviderSkill` (gated on `has_provider_profile` via `ListSkill` capability → precondition_unmet), `SetServiceArea` (location_tracking consent). **Wired REAL fact resolvers** in AccessServiceProvider: has_provider_profile, skill_listed, identity_verified (from verification_tier) — P0-17 now data-driven. 5 tests |
| P1-09 | Filament 5 admin panel + mandatory 2FA + admin roles | **DONE** — Filament 5.7, `/admin` gated by `canAccessPanel` (staff roles only, never customer/provider), **mandatory TOTP 2FA** (`multiFactorAuthentication(..., isRequired: true)` — can't reach dashboard un-enrolled), recovery codes; `ProviderProfileResource`; User implements FilamentUser/HasName/HasAppAuthentication(+Recovery); **Spatie teams turned OFF** (org roles live in `memberships.role` per doc 02; Spatie = global staff only); 4 tests |
| P1-10 | DSAR export + crypto-shred erasure | **DONE** — per-party `data_key` (encrypted, minted on party creation); `ErasePartyData` destroys the key, nulls/tombstones identifiers, deletes PII rows, but KEEPS the party row + id (ledger FKs survive) and announces `party.erased` via outbox; `ExportPersonalData` (DSAR); `DELETE /v1/me`, `GET /v1/me/data-export`; 3 tests |

### Phase 2 — Jobs, offers, engagements (direct booking)

| ID | Task | Status |
|---|---|---|
| P2-01 | jobs + engagement_mode + conditional-address CHECK + JobStateMachine | **DONE** — `service_jobs` table (named to avoid Laravel's queue `jobs`; doc 02 corrected), enums engagement_mode/assignment_mode/job_status; DB CHECK `(engagement_mode='remote' OR address_id IS NOT NULL)` — remote saves NULL, onsite/hybrid without address rejected by DB; `JobStateMachine` (full transition matrix, illegal→`IllegalJobTransition`); 9 tests |
| P2-02 | EngagementModePolicy (feature applicability object) | **DONE** — single class encoding the doc-06 matrix (address/dispatch/check-in/panic/share/site-visit/deliverables per mode); mode-branching removed from the enum; scan test proves no `=== 'remote'` branching anywhere else in app/; 3 tests |
| P2-03 | job creation + photos + PII-minimised JobResource | **DONE** — `CreateJob`/`PublishJob` actions (reference gen, draft→open via state machine + outbox); `job_photos`; `JobResource` PII minimisation — owner sees exact address, a pre-engagement provider sees only quarter/city (no line1/coordinates/landmark); draft hidden from non-owners; address must belong to caller; 6 tests |
| P2-04 | provider search (ST_DWithin + skill + rating/tier; skips geo for remote) | **DONE** — `ProviderSearch::forJob`: skill match + service-area coverage (ST_DWithin) for onsite/hybrid, **NO geo for remote** (whole skilled pool); filters suspended + (when required) unverified; ranks by tier then rating; `GET /v1/jobs/{job}/providers` (owner only); 5 tests incl. remote-outside-any-radius |
| P2-05 | job_offers + customer_direct origin + expiry | **DONE** — `job_offers` (offer_origin/offer_status enums, UNIQUE(job,provider), **partial unique `one_accepted_offer_per_job`** — P2-06 backbone); `CreateDirectOffer` (open→offered + outbox); `offers:expire` command; `POST /v1/jobs/{job}/offers` (owner only); 6 tests |
| P2-06 + P2-06b + P2-07 | AcceptOffer (concurrency-safe), engagements, mode-keyed gate, auto-assign lead | **DONE** — `engagements` (one per job: `job_id UNIQUE`) + `assignments` (assignment_role/status enums, `UNIQUE(engagement,worker)`, partial unique `one_lead_per_engagement`); `AcceptOfferAction`: fact gate (`AcceptPaidJob`) BEFORE the txn, then `lockForUpdate` on the job + status guard → offer accepted, siblings superseded, job offered→engaged, engagement created, outbox `engagement.created`; **individual provider auto-assigned as `lead`** (company → dispatcher, P2-08); `identity_verified` resolver now `max(phone→1, ID tier)` so remote passes the lighter check while on-site needs full ID; `POST /v1/offers/{offer}/accept`; 8 tests incl. **20-way race → exactly 1 engagement** and mode-keyed gate (on-site unverified → `precondition_unmet`, remote OK) |
| P2-08 | dispatcher assignments + org-boundary authorisation | **DONE** — `EngagementPolicy` (org-internal RBAC: active owner/admin/dispatcher of the provider org, or the individual provider themselves — never the fact model); `AssignWorker`/`UnassignWorker` actions (soft removal via `removed_at`, frees the lead slot); worker↔provider boundary enforced BOTH in the Action (clean 422) AND by DB constraint trigger `assignments_worker_boundary_check` (individual → provider's own user; org → active membership); `AssignmentConflict` (409) for second-lead/duplicate; `POST`/`DELETE /v1/engagements/{engagement}/assignments`; 8 tests incl. **dispatcher-of-A-cannot-assign-worker-of-B (422) + DB-trigger backstop** |
| P2-09 | worker availability + conflict detection | **DONE** — assignments carry an optional booking window (`scheduled_from`/`scheduled_to`); a GENERATED `tstzrange` feeds a GiST `EXCLUDE` constraint (`btree_gist`) `assignments_no_double_booking` so one worker can never hold two active overlapping bookings — the hard guarantee; `AssignWorker` pre-checks overlap (clean 409 `WorkerDoubleBooked`) and catches the constraint under a race; half-open `[)` ranges mean touching windows don't conflict; removed assignments free the slot; 7 tests incl. **overlap → 409, adjacent OK, DB-constraint backstop** |
| P2-10 | Filament: jobs, offers, engagements + manual reassignment | **DONE** — read-only admin resources (`JobResource`/`JobOfferResource`/`EngagementResource`) under a "Marketplace" nav group: list + view + filters, no create/edit routes so status/money can't be mutated outside the state machines/Actions (rule #8/#9); manual (re)assignment via an `AssignmentsRelationManager` on the engagement whose assign/remove route through the same `AssignWorker`/`UnassignWorker` Actions (org boundary + one-lead + no-double-booking still enforced; domain problems shown as notifications); staff-gated + 2FA (P1-09); 4 tests |

**Phase 2 (marketplace core) complete.**

### Phase 2.5 — Negotiation and quotations

| ID | Task | Status |
|---|---|---|
| P2.5-01/02/03 | versioned immutable quotations + one-live index + three dates | **DONE** — `quotations` + `quotation_lines`; server-computed subtotal; `assert_quote_terms_immutable` (UPDATE of a non-draft quote's terms throws) + `assert_quote_lines_frozen` (lines frozen after submit) triggers; partial unique `one_live_quote_per_provider_per_job`; the three dates (requested/estimated/committed — only `provider_committed_at` will feed on-time-rate); `QuotationStateMachine`; `SubmitQuotation` + `ReviseQuotation` (revise = supersede + v+1 via `supersedes_id`, shared `QuotationBuilder`: draft→add lines→submit); `POST /v1/jobs/{job}/quotations`, `POST /v1/quotations/{quotation}/revise`; 9 tests incl. **DB-frozen terms/lines, revision chain, one-live** |
| P2.5-05 | accept quotation → engagement + milestones | **DONE** — the quote path into `engagements` (converges with the offer path): `engagements.offer_id` now nullable + new nullable `quotation_id` + CHECK one origin present; `milestones` table; **deferred constraint trigger `milestones_sum_matches_agreed`** (SUM = agreed, only when milestones exist — offer-path engagements carry none); `AcceptQuotation` (customer-authorized, job-locked, quote→accepted, competing live quotes rejected, job→engaged, auto milestone plan deposit@0 + balance, lead auto-assign); `JobStateMachine` now allows open→engaged; shared `LeadAssigner` (also used by AcceptOfferAction); `POST /v1/quotations/{quotation}/accept`; 7 tests incl. **deferred SUM enforcement (SET CONSTRAINTS IMMEDIATE)** |
| P2.5-04 | site visits (chargeable + creditable) | **DONE** — `site_visits` table (native `site_visit_status`; CHECK ties `is_chargeable` to a positive fee); `ScheduleSiteVisit`/`CompleteSiteVisit` actions (provider-owned; complete links the `resulting_quotation_id`); on acceptance a **completed chargeable visit's fee is credited** against the engagement — agreed = subtotal − fee, recorded on `engagements.visit_credit_minor`, and the deposit milestone reduced by the credit (milestones still sum to agreed); `POST /v1/jobs/{job}/site-visits`, `POST /v1/site-visits/{siteVisit}/complete`; 9 tests incl. **fee credited on acceptance + scheduled-not-completed not counted** |
| P2.5-06 | `quote_expiring` / `quote_pending_customer` follow-ups | **deferred to Phase 7** — needs the follow-ups scheduling/dedupe/budget machinery (doc 07); its natural home |

**Phase 2.5 complete except P2.5-06 (parked for Phase 7).**

### Phase 3 — Money

| ID | Task | Status |
|---|---|---|
| P3-01 | ledger tables + balance trigger + append-only + REVOKE | **DONE** — `ledger_accounts`/`ledger_transactions`/`ledger_entries` (native `account_kind`/`entry_direction`/`txn_kind` enums; accounts unique on `(party_id, kind, currency)` NULLS NOT DISTINCT); **append-only enforced by a trigger** (`forbid_ledger_mutation` raises on UPDATE/DELETE — role-independent, since dev runs as superuser where REVOKE no-ops) + REVOKE for prod; **deferred balance constraint** `ledger_must_balance` (SUM debit == SUM credit); `amount_minor > 0` CHECK; `ledger_balances` view; `Ledger` posting service (account resolver + balanced `post()`, app-level `UnbalancedTransaction` guard) with `LedgerEntryInput`/`AccountKind`(normal-balance)/`EntryDirection`/`TxnKind`; sign convention in `Money`; 10 tests incl. **UPDATE/DELETE raise at DB, deferred-balance rejection, amount>0** |
| P3-02..P3-15 | balances view rebuild, gateway, intents, webhooks, escrow, payouts, reconciliation, cash | not started |

Phases 3–8: not started (see build plan).

## What was done, most recent first

- **P3-01 — the double-entry ledger**: `ledger_accounts`/`transactions`/`entries` with the money
  invariants enforced by Postgres, not app code — append-only via a trigger that raises on
  UPDATE/DELETE (role-independent; the doc's REVOKE is a no-op under the dev superuser, so the
  trigger is the real guard), a **deferred** balance constraint (debits == credits per transaction),
  and `amount_minor > 0`. Balances are computed (`ledger_balances` view + `LedgerAccount::balanceMinor`).
  A `Ledger` service is the only writer: it resolves accounts and posts balanced transactions,
  rejecting an unbalanced set (`UnbalancedTransaction`) before the DB does. Sign convention lives in
  `Money`. 10 tests, incl. DB-level append-only and deferred-balance rejection. Backend 205 tests
  green, PHPStan L6 clean, Pint clean.

- **P2.5-04 — site visits (chargeable + creditable)**: `site_visits` (native status enum; a CHECK
  ties chargeable to a positive fee), `ScheduleSiteVisit`/`CompleteSiteVisit` Actions. A completed
  chargeable visit linked to the accepted quote has its fee credited on the engagement — agreed =
  subtotal − fee (recorded on `engagements.visit_credit_minor`), with the deposit milestone reduced
  by the credit while milestones still sum to the agreed amount. `POST /v1/jobs/{job}/site-visits`
  and `/v1/site-visits/{siteVisit}/complete`. **P2.5-06 (quote follow-ups) deferred to Phase 7**
  (needs the follow-ups scheduling machinery). OpenAPI + TS client updated. Backend 195 tests green,
  PHPStan L6 clean, Pint clean.

- **P2.5-05 — accept quotation → engagement + milestones**: the customer accepts a submitted quote,
  forming the engagement (the quote path converging with the offer path — `engagements` now takes a
  nullable `offer_id`/`quotation_id` with a one-origin CHECK) plus an auto milestone plan (deposit at
  position 0, then balance). The `SUM(milestones) = agreed_amount` invariant is a **deferred**
  constraint trigger (fires only when milestones exist, so offer-path engagements are unaffected).
  `AcceptQuotation` is job-locked and concurrency-safe, rejects competing live quotes, and engages
  the job (JobStateMachine gained open→engaged). Extracted a shared `LeadAssigner` used by both accept
  paths. `POST /v1/quotations/{quotation}/accept`. OpenAPI + TS client updated. Backend 186 tests
  green, PHPStan L6 clean, Pint clean.

- **P2.5-01/02/03 — versioned, immutable quotations**: `quotations` + `quotation_lines` with a
  server-computed subtotal, DB triggers that freeze a submitted quote's terms and lines (an UPDATE
  throws), a partial unique index allowing only one live quote per provider per job, and the three
  distinct dates. `SubmitQuotation`/`ReviseQuotation` Actions (revision supersedes and creates the
  next version via `supersedes_id`, sharing a `QuotationBuilder` that writes draft → lines → submit)
  behind `POST /v1/jobs/{job}/quotations` and `/v1/quotations/{quotation}/revise`. OpenAPI + TS
  client updated. Backend 179 tests green, PHPStan L6 clean, Pint clean.

- **P2-10 — Filament marketplace resources + manual reassignment**: read-only admin views for
  jobs, offers and engagements (list/view/filters, no create/edit so state/money never bypass the
  state machines) under a Marketplace nav group; an assignments relation manager on the engagement
  provides manual (re)assignment that routes through the AssignWorker/UnassignWorker Actions, so the
  org boundary, one-lead and no-double-booking rules still hold. Staff-gated + 2FA. **This completes
  Phase 2.** Backend 170 tests green, PHPStan L6 clean, Pint clean.

- **P2-09 — worker availability + double-booking prevention**: assignments gained an optional
  booking window (`scheduled_from`/`scheduled_to`); a GENERATED `tstzrange` drives a GiST `EXCLUDE`
  constraint (`btree_gist`) so one worker can never hold two active overlapping bookings — enforced
  at the DB regardless of the caller. `AssignWorker` pre-checks and returns a clean 409
  (`WorkerDoubleBooked`), catching the constraint under a race. Half-open ranges mean back-to-back
  windows are fine; removing an assignment frees its slot. OpenAPI + TS client updated. Backend
  **166 tests green**, PHPStan L6 clean, Pint clean.

- **P2-08 — dispatcher assignments + org boundary**: `EngagementPolicy` gates staffing on
  org-internal RBAC (dispatch authority = active owner/admin/dispatcher of the provider org, or the
  individual provider). `AssignWorker`/`UnassignWorker` actions; the worker↔provider boundary is
  enforced twice — a friendly 422 in the Action and, as the hard guarantee, the DB constraint
  trigger `assignments_worker_boundary_check` (so "dispatcher of A can't assign a worker of B" holds
  even if the app is bypassed). Second-lead / duplicate-worker → 409. Soft removal (`removed_at`)
  frees the `one_lead_per_engagement` slot. `POST`/`DELETE /v1/engagements/{engagement}/assignments`;
  OpenAPI + TS client updated. Backend **159 tests green**, PHPStan L6 clean, Pint clean.

- **P2-06 + P2-06b + P2-07 — accept offer → engagement (concurrency-safe, mode-gated)**: added
  `engagements` (one per job) + `assignments` tables, `AssignmentRole`/`AssignmentStatus` enums,
  `Engagement`/`Assignment` models + factories, `AcceptOfferAction` (row-lock + status guard +
  DB `job_id UNIQUE`/partial-unique backstops → exactly one engagement under a 20-way race),
  `EngagementResource`, `POST /v1/offers/{offer}/accept`. The accept-paid-job gate keys the
  required identity tier on `engagement_mode` (remote → phone-only tier 1, on-site → full ID
  tier 2/3); the `identity_verified` resolver now takes the stronger of the phone check and the
  provider's ID tier. Individual providers auto-assign as `lead`. OpenAPI updated + TS client
  regenerated. Also fixed a pre-existing float-precision flake in `JobCreationTest`. Backend
  **151 tests green**, PHPStan L6 clean, Pint clean.

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

**Phases 0 and 1 are COMPLETE** (every task committed; 114 tests green). End-of-P1 demo works: a
provider signs up by phone (OTP), lists skills, sets a service radius; an admin sees them in
Filament. Next is **Phase 2 — jobs, offers, engagements (direct booking)**:

1. **P2-01** jobs + engagement_mode + conditional-address CHECK + JobStateMachine
2. **P2-02** EngagementModePolicy (feature applicability object, doc 06)
3. **P2-03..P2-10** job creation + PII-minimised resource; provider search (ST_DWithin + skill +
   rating, skips geo for remote); offers; **AcceptOfferAction** (concurrency: 20 parallel → 1
   engagement); AcceptPaidJob gate keyed to engagement_mode; engagements + auto-assign; assignments
   + dispatcher org-boundary; availability/conflict; Filament.

Follow-ups noted in code (not blocking): `cap add android/ios` when building native; full
Tailwind/Vite pipeline for Blade (token CSS linked directly for now); the identity-verification
approval flow that raises `verification_tier` (P6).

## Open decisions / to confirm with user

- **P0-09 hosting region: DECIDED → in-country (Cameroon)**, Option A. Lawyer sign-off + CNDP
  processing register still pending (founder tasks). Self-managed PostGIS/Redis/MinIO in-country.
- **Ionic flavour: DECIDED → Angular + TypeScript** (ngx-translate for i18n).
- Redis: running as a Windows service on :6379 (confirmed). Using **predis** client (no phpredis
  extension). Note: `php artisan horizon` needs pcntl (not on Windows) — dashboard + config work
  locally; the Horizon supervisor runs on Linux in prod/CI. Local queues: `queue:work redis`.
