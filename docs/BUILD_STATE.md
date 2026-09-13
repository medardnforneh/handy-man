# Build State

> Living tracker for the build. Updated as work progresses. Source of truth for **where we
> are** and **how this machine is set up**. Read this first when resuming.

_Last updated: 2026-09-12 (the MODERNIST design system landed on all three surfaces — Archivo,
a red accent, zero radius, 2px rules, flush-left buttons, nothing floats — and then its icon set,
Lucide; grayscale imagery is the one item deliberately not applied. Before that: the
reachability sweep re-run and CLOSED)_

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
| P1-02 | OTP signup/login + rate limits | **DONE** — `otp_challenges` (hashed codes), `RequestOtp`/`VerifyOtp` actions, phone 3/hr + IP 10/hr + device 5/hr limits, hard-lock after 5 wrong verifies (attempt increment persists outside txn), find-or-create user on first verify; `POST /v1/auth/otp/{request,verify}`; 6 tests incl. 4th-request-rejected. **The code reaches a phone since 2026-09-13**: `OTP_SENDER=sms` → `SmsOtpSender` over the SMS rail (`OtpOverSmsTest`); before that the only sender was the log, in every environment. Also fixed pgsql session **timezone→UTC** (timestamptz was shifting by +1h, breaking tight windows) |
| P1-03 | Sanctum access + rotating refresh tokens + reuse detection | **DONE** — Sanctum 15m access tokens (uuid tokenable); `refresh_tokens` (opaque 256-bit, sha256-hashed, 30d, family rotation); `IssueAuthTokens`/`RotateRefreshToken`; **replaying a rotated token revokes the whole family + wipes access tokens** (revocation persists outside txn); tokens issued on OTP verify; `POST /v1/auth/refresh`, `GET /me`, `POST /logout`; reference routes → `auth:sanctum`; `ProblemAware` interface unifies error rendering; 7 tests |
| P1-04 | devices registration + push token capture | **DONE** — `devices` (id == client X-Device-Id, upsert), `RegisterDevice` action (moves a push token to the newest device), `POST /v1/devices` (auth:sanctum); captures platform/push_token/app_version; 5 tests |
| P1-05 | consents (granular/versioned/revocable + presented_locale) | **DONE** — append-only `consents` log (terms/privacy/location_tracking/id_verification/marketing), policy_version, presented_locale (CHECK fr/en); `ConsentState` (latest-per-purpose), `ConsentGuard` blocks geo writes when location_tracking revoked → `consent_required` problem+json (`missing_purpose`); `GET/POST /v1/consents`; `ProvidesProblemExtras` interface |
| P1-05b | locale + comms_locale prefs | **DONE** — columns already on users (P1-01); `PATCH /v1/me/preferences` sets UI + comms locale independently (locale=en, comms_locale=fr round-trips); app-side first-launch offer via `LocaleService` (P0-12) |
| P1-06 | addresses + PostGIS + GIST index | **DONE** — `addresses` with `geography(Point,4326)` + GIST spatial index (via matanyadaev/laravel-eloquent-spatial); `Address::scopeNear` uses **ST_DWithin** (index-served, parameterized); `CreateAddress` gated on location_tracking consent; `GET/POST /v1/addresses`; 5 tests (proximity correctness + index existence). **Acceptance criterion MET 2026-08-09**: `php artisan perf:geo-benchmark` — 100k seeded addresses, index-served, **p95 10.0ms** against the 50ms budget (a 5km radius is a different story — see the audit entry below) |
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
| P2.5-06 | `quote_expiring` / `quote_pending_customer` follow-ups | **DONE** (Phase 7) — the orchestrator schedules `quote_pending_customer` (+24h) and `quote_expiring` (24h before `valid_until`) on `quote.submitted` (the highest-ROI nudge — the lead is already paid for), and cancels both on `quote.accepted`; `quote.revised` cancels the superseded quote's nudges and schedules the new quote's. 2 tests |

**Phase 2.5 complete.**

### Phase 3 — Money

| ID | Task | Status |
|---|---|---|
| P3-01 | ledger tables + balance trigger + append-only + REVOKE | **DONE** — `ledger_accounts`/`ledger_transactions`/`ledger_entries` (native `account_kind`/`entry_direction`/`txn_kind` enums; accounts unique on `(party_id, kind, currency)` NULLS NOT DISTINCT); **append-only enforced by a trigger** (`forbid_ledger_mutation` raises on UPDATE/DELETE — role-independent, since dev runs as superuser where REVOKE no-ops) + REVOKE for prod; **deferred balance constraint** `ledger_must_balance` (SUM debit == SUM credit); `amount_minor > 0` CHECK; `ledger_balances` view; `Ledger` posting service (account resolver + balanced `post()`, app-level `UnbalancedTransaction` guard) with `LedgerEntryInput`/`AccountKind`(normal-balance)/`EntryDirection`/`TxnKind`; sign convention in `Money`; 10 tests incl. **UPDATE/DELETE raise at DB, deferred-balance rejection, amount>0** |
| P3-02 | `ledger_balances` cache + rebuild-from-entries test | **DONE** — cache is a MATERIALIZED VIEW `ledger_balances_cached` (derived, always rebuildable); `php artisan ledger:rebuild-balances` refreshes it from the entries; test rebuilds and asserts **cached == live view == computed balance** across accounts, before and after further postings |
| P3-03 | `PaymentGateway` interface + one aggregator (CinetPay) | **DONE** — `PaymentGateway` abstraction (requestCollection/requestPayout/fetchStatus/verifyWebhook/parseWebhook) with normalised DTOs (`CollectionRequest`/`PayoutRequest`/`GatewayResult`/`GatewayEvent`/`GatewayStatus`); **CinetPay** adapter (v2/payment + v2/payment/check, status mapping, HMAC-SHA256 `x-token` webhook verification over the documented field order) and an in-memory **FakeGateway** that drives the whole flow deterministically (`settle()`); config-selected via `MoneyServiceProvider` (`config/payments.php`, default `fake`) — the app never names a provider; 7 tests (Http-faked request building + status mapping + signature verify + config selection). Live sandbox end-to-end pends real CinetPay credentials. |
| P3-04 | `payment_intents` + USSD-push flow + pending UX contract | **DONE** — `payment_intents` (native `payment_status`/`payment_purpose`; unique `idempotency_key`; unique `(gateway, external_ref)`; amount>0); `InitiatePaymentIntent` (idempotent — same key returns the same intent; calls the gateway; rests in `processing`; 15-min expiry); `POST /v1/payment-intents` (tied to the request Idempotency-Key); `PaymentIntentResource` is the pending-UX contract (status + `expires_at` countdown + `payment_url`) |
| P3-05 | webhook handler: signature → dedupe → locked apply | **DONE** — `payment_events` UNIQUE (gateway, external_ref, event_type) is the duplicate defence; `ProcessPaymentWebhook` verifies signature (invalid → recorded + 401), dedupes by insert (savepoint-wrapped so a conflict → 200 without aborting), then locks the intent and **re-fetches the authoritative status** (never trusts the callback body) and applies once; `ApplyGatewayResult` posts the collection (DR gateway_receivable / CR liability) idempotently; public `POST /v1/webhooks/payments/{gateway}` (idempotency-exempt); 6 tests incl. **10 duplicate webhooks → exactly 1 ledger transaction**, unsigned → 401, post-terminal no-op |
| P3-06 | reconciliation poller with backoff; timeout → expired | **DONE** — `payments:reconcile` sweeps unresolved intents and asks the gateway for the authoritative status (`ReconcilePaymentIntent`: lockForUpdate, idempotent apply), so a **lost webhook still resolves via poll**; an intent still pending past `expires_at` is force-expired (timeout is a state, posts nothing); scheduled on a backoff cadence; 3 tests |
| P3-07 | lead credits: purchase + spend flows | **DONE** — purchase lands via the collection path (intent → DR gateway_receivable / CR lead_credit_liability); `SpendLeadCredits` shrinks the liability and books revenue (DR lead_credit_liability / CR platform_revenue), with **overspend prevented by locking the credit account row** (serialised check-and-post, balance can't go negative); `InsufficientLeadCredits` (422 + shortfall); `Ledger::availableMinor` reads a balance in its natural direction; `GET /v1/provider/credits`; 4 tests |
| P3-08 | payouts + failure reversal (new balanced txn, never a delete) | **DONE** — `payouts` table (idempotent; reserves funds via the pending ROW, not a ledger entry); `RequestPayout` locks the payable account + subtracts already-reserved pending payouts so the balance can't be double-spent (`InsufficientPayable` 422); `ResolvePayout` posts DR provider_payable / CR platform_cash **only on gateway confirmation** (via `payouts:reconcile`); `ReversePayout` corrects a confirmed-then-failed payout with a NEW mirror transaction (`reversal_transaction_id`), **never a delete** — restoring provider_payable to its pre-payout value; `POST /v1/provider/payouts`; 5 tests incl. **reversal restores the pre-payout balance with both txns intact** |
| P3-10 + P3-14 | escrow: collection, release-on-approval, refund | **DONE** — collection already lands via the intent path (purpose `escrow` → DR gateway_receivable / CR escrow_liability, referenced to the engagement); `Ledger::escrowHeldMinor` computes per-engagement escrow from the transaction reference; `ApproveMilestone` releases a milestone's slice (DR escrow_liability / CR provider_payable net / CR platform_revenue at 15% commission), serialised per engagement by an advisory lock, idempotent, **partial approval leaves the remainder escrowed**; `RefundEngagement` returns remaining escrow (DR escrow_liability / CR platform_cash); `InsufficientEscrow` (409) when unfunded; `POST /v1/milestones/{milestone}/approve`, `POST /v1/engagements/{engagement}/refund` (customer-gated); 7 tests |
| P3-12 | property test: global debits == credits over random flows | **DONE** — drives a deterministic 60-step randomized mix of real flows (credit purchase/spend, payable grants, payouts + reversals, with legitimate refusals caught) and asserts **SUM(debits) == SUM(credits) globally after every step** — the ledger never leaks or invents a franc |
| P3-15 | cash settlement recording | **DONE** — new `provider_receivable` (asset) account kind; `cash_settlements` table; `RecordCashSettlement` books the 15% commission as revenue AND as a provider debt (DR provider_receivable / CR platform_revenue) and marks a named milestone paid — no escrow involved; provider-gated `POST /v1/engagements/{engagement}/cash-settlements`; 3 tests. Makes honest self-reporting first-class (builds provider history) |
| P3-09 | nightly reconciliation + `reconciliation_exceptions` + admin alert | **DONE** — `reconcile:nightly` resolves stuck payments/payouts (via the pollers), then integrity-checks the ledger: a succeeded intent missing its ledger txn, and (with `--wallet-cash`) a `platform_cash` vs wallet mismatch → a `reconciliation_exceptions` row + outbox `reconciliation.exception` alert, **never auto-corrected**; one-open-per-(kind,ref) partial unique index dedupes re-runs; `ResolveReconciliationException` applies a human's balanced adjustment stamped with `created_by_user_id`; 5 tests |
| P3-11 | auto-approve timer (72h) | **DONE** (Phase 7) — `AutoApproveDeliverables` + `deliverables:auto-approve` command auto-accept a submitted deliverable left un-reviewed past the window (72h, config) through the same `ReviewDeliverable` Action (narration/outbox/escrow fire as a manual accept would); the orchestrator schedules an `auto_approve_warning` follow-up 24h before and cancels it on review. 2 tests |
| P3-13 | deposit-capture-on-agreement | **DONE** — `CaptureDepositOnAgreement` collects the deposit (the position-0 milestone) into escrow the moment an engagement forms, riding the committed `engagement.created` outbox seam so the gateway call lands outside the acceptance txn (`CaptureDepositOnEngagement` listener, wired in `MoneyServiceProvider::boot`). Idempotent on a deterministic key (`deposit-capture:{engagement}`) so the at-least-once relay never double-charges; offer-path engagements carry no milestones so capture nothing. 3 tests |

**Phase 3 (Money) complete** — P3-11 (auto-approve timer) landed on the follow-up engine and P3-13 (agreement-time deposit capture) is done.

### Phase 4 — The engagement workspace

| ID | Task | Status |
|---|---|---|
| P4-01 + P4-02 + P4-09 | conversations/messages + server-narrated structured messages + contact detection | **DONE** — `conversations`/`conversation_participants`/`messages` (native `message_kind` enum, `payload`, reply/edit/delete, receipts + reactions tables); `Narrator` emits structured messages **inside the transition's transaction** (rule #11) so a rollback narrates nothing, `ConversationManager` enrols participants when an engagement forms; AcceptQuotation narrates `quote_accepted`; the message endpoint accepts **only free-form `text`** (a client posting `quote_accepted` → 422) and gates on participation; contact details (phone/email) detected → `contact_flag`, logged not blocked (P4-09); `GET`/`POST /v1/jobs/{job}/messages`; 8 tests incl. **server-narration + rolled-back-narrates-nothing + client-structured-rejected** |
| P4-03 | Reverb `engagement.{id}` channel + participant Policy | **DONE** — `ChannelAccess::isEngagementParticipant` now resolves the engagement → its job conversation and authorizes only participants (the P0-16 deny-by-default stub is real); a non-participant or unknown engagement is rejected, and `/broadcasting/auth` refuses unauthenticated private subscriptions; 4 tests |
| P4-08 | `deliverables` submit/accept/reject (remote path) | **DONE** — `deliverables` (native `deliverable_status`; `media_url` until the P4-05 media table); `SubmitDeliverable` (provider; narrates `deliverable_submitted` in-transaction) + `ReviewDeliverable` (customer accept/reject with reason, row-locked, once-only); provider/customer-gated `POST /v1/engagements/{engagement}/deliverables` + `/deliverables/{deliverable}/review`; 7 tests |
| P4-06 | Ionic workspace UI | **DONE** — the thread renders free-form bubbles + every server-narrated lifecycle kind as a system chip; composer posts free-form text only (rule #11) |
| P4-04 (live messages) + P4-07 | live message delivery + reconnect reconciliation | **DONE** — `MessagePosted` broadcast off the committed outbox on `private-engagement.{id}`; client subscribes after fetching, dedupes on id, and refetches on channel re-subscribe / foreground. Verified against a real Reverb kill-and-restart |
| P4-04 (remainder) | typing indicators + presence | **DONE** — both landed and were verified end to end with a second authorized client; see the entries below. Typing is a whisper (client event, never persisted), presence is its own channel alongside the private one |
| P4-05 | voice notes | **DONE (backend + client)** — first-class `voice` messages with attached audio, plus the media access rail (`GET /media/{media}`) that report photos also needed. Mic capture and in-app playback decode are unverified — see the entry below |

### Phase 5 — Execution (provider section + native capabilities)

| ID | Task | Status |
|---|---|---|
| P5-03 + P5-06 | `work_sessions` check-in/out (geo + timestamp) + structured provider status actions | **DONE** — `work_sessions` (geography start/end points + GPS accuracy; `work_sessions_span_check`; **partial unique `one_open_session_per_assignment`** — can't check in twice); `CheckIn` opens a session and narrates `arrived` in-transaction (rule #11), gated to onsite/hybrid via `EngagementModePolicy` (**remote → 422 `check-in-not-supported`**, no affordance); `CheckOut` row-locks and closes the open session (pure work-time close — completion stays a separate signal); `RecordStatus` narrates the structured `ProviderStatus` signals (on_the_way/started/paused/resumed/completed — `arrived` reserved to check-in); acting user must be an active assigned worker (provider section queries `assignments` only, no individual-vs-company branch); `POST /v1/engagements/{engagement}/check-in`, `/check-out`, `/status`; OpenAPI + TS client updated; 8 tests incl. **remote-refuses-check-in, double-check-in 409, arrived-not-postable-via-status**. Later joined by the read side: `GET /v1/provider/work/{engagement}` + `WorkProgress` (checked_in / current_status / report_submitted all DERIVED from the rows the Actions write), authorised by the same active-assignment boundary; 4 tests |
| P5-04 | `job_reports` + before/after `media`, EXIF stripped server-side | **DONE** — polymorphic `media` table (owner party, attachable type/id, kind, sha256, bytes, `captured_point` geography; CHECKs on bytes/type/kind) + `job_reports` (summary, materials jsonb, extra_charges, signature slot); `StoreMedia` **re-encodes raster images through GD to strip every EXIF/XMP/GPS segment**, records the client-reported geo in `captured_point` server-side (never in the file), and stores sha256/bytes of the CLEAN file; `SubmitJobReport` (worker; attaches before/after photos in one txn); `POST /v1/engagements/{engagement}/report` (multipart); OpenAPI + TS client updated; 4 tests incl. **injected-EXIF-marker gone from stored bytes + geo-in-DB** |
| P5-05 | Push notifications (FCM) via outbox | **DONE** — provider-agnostic `PushSender` abstraction + normalised `PushMessage`; `FakePushSender` (records sends, default) + `FcmPushSender` (HTTP v1, one request/token, per-token failure logged not thrown — live delivery pends real project creds); config-selected in `NotificationsServiceProvider` (`config/notifications.php`, default `fake`). Push **rides the transactional outbox**: `NotifyOnOutboxMessage` subscribes to the `OutboxMessagePublished` seam and, for a relayed `message.created`, notifies the conversation's participants **except the sender** on their non-revoked devices, **each in their own comms locale** (`push.*` i18n keys, parity OK). New endpoints: none (server-internal). 4 tests incl. **sender-excluded + per-locale copy + sole-participant no-op** |
| P5-02 | offline-first cache + write queue with idempotency keys | **DONE** — `core/offline/`: a small IndexedDB wrapper (`idb.ts`, two stores — a disposable `cache` and a never-evicted `outbox`), `ConnectivityService` (browser events as hints, the requests themselves as evidence), `OfflineCache` (read-through: last server answer beats a fixture, fixture beats a spinner) and `WriteQueue`. **The acceptance property is one idempotency key minted at enqueue and reused on every replay**, so a write the server already took is recognised rather than repeated. Persist-before-attempt, strict FIFO (never skip a backing-off entry — a thread that reorders is worse than one that pauses), 5xx/429 retried with backoff, other 4xx terminal with the problem+json `detail` surfaced, outbox dropped on logout (it would otherwise replay under the next user's Bearer). Queued today: chat messages, check-in/out, provider status, milestone approval; cached: jobs, job detail, thread, skills taxonomy, provider work detail. **`ion-icon`/`ion-spinner` are deliberately absent from the offline UI** — their assets load lazily, i.e. exactly what a dead network can't deliver (observed as a ChunkLoadError). 5 Karma specs (real Chrome, real IndexedDB) + a live browser run — see the entry below |
| P5-01 | Ionic PWA/Android/iOS + secure token storage | **DONE (PWA + Android; iOS declared, not built)** — `@angular/service-worker` precaches the shell, every JS chunk **and `/assets/i18n/*.json`** (the stock config omits them, which offline means every label renders as a raw key), disabled on native since a packaged app already serves assets locally. Install manifest is **generated from `tokens/tokens.json`** like every other surface, and the token build now **fails if `index.html`'s two `theme-color` metas drift from the brand token** — the one place a colour must be a literal and the no-literal-colour lint can't reach. `core/secure-store.ts` puts the **refresh token in the OS store** (Android EncryptedSharedPreferences / iOS Keychain via `@aparajita/capacitor-secure-storage`), falling back to Preferences on web with the limitation stated rather than hidden, and migrating an existing plaintext token on first launch. **Native API/socket URLs**: a packaged app's origin is the device, so the prod build's relative `/api/v1` and `window.location.hostname` would never leave the phone — `nativeApiBaseUrl`/`nativeHost` are now chosen at runtime via `Capacitor.isNativePlatform()`. Verified: app opens and renders **fully offline with translations resolved** (real browser, network cut at CDP level), and `gradlew assembleDebug` produces a **5.3 MB APK** against the local SDK. **iOS is not built here** (needs macOS/Xcode) — the codebase is platform-agnostic and `npx cap add ios` is the whole step |

**Phase 5 complete.** P5-01's remaining external dependency is the deployed origin: `NATIVE_API_ORIGIN`
in `environment.prod.ts` is a **placeholder** (`https://app.handyman.cm`) because no domain is
registered yet — it must be set before the first store build, or the native app will call nothing.

### Phase 6 — Trust, safety, reputation

| ID | Task | Status |
|---|---|---|
| P6-01 | `verification_documents` + encrypted bucket + signed 60s URLs | **DONE** — `verification_documents` (native `doc_kind`/`doc_status`; `grants_tier` fixed by kind so tier can't be self-assigned; reject-reason CHECK) stored in a **separate `verification` disk, encrypted at rest by the app** (`VerificationStorage` via `Crypt` — on-disk bytes are never the plaintext even if the bucket is misconfigured); sha256 of the plaintext recorded. Access is a **signed short-TTL app route** (`SignedDocumentUrl`, 60s; `GET /verification-documents/{document}/view` under `signed` middleware) — deliberately through the app, not a presigned bucket URL, so P6-02 can log every view. `SubmitVerificationDocument`; `GET`/`POST /v1/verification-documents` (paths never returned). OpenAPI + TS client. 5 tests incl. **encrypted-at-rest, signed-URL streams within TTL, expired→403, tampered→403** |
| P6-02 | Filament review queue; every document **view** writes an activity log | **DONE** — append-only `activity_logs` (DB trigger forbids UPDATE/DELETE; actor/action/subject/context/ip) + `ActivityLogger`; the signed-URL **view controller logs every view** (`verification_document.viewed`, capturing the admin + IP) — reads, not just edits (the insider-threat control). Filament `VerificationDocumentResource` under a "Trust & safety" nav group: oldest-pending-first queue, pending badge, Open-document (signed URL) / Approve / Reject actions routing through the domain Action (never a row edit). 1 test (view-is-logged) |
| P6-03 | Verification tiers feed `identity_verified`; `AcceptPaidJob` reads tier from job mode + skill `risk_tier` | **DONE** — `ReviewVerificationDocument` (approve/reject, reviewer-attributed, once-only) **raises the party's `verification_tier` to the tier the document grants** and invalidates the cached `identity_verified` fact; the gate (already mode+risk-keyed) now becomes fully data-driven from real approved documents. 3 tests incl. **tier-1 provider refused a tier-3 on-site job, allowed after approval; a remote high-risk job needs only the lighter check** |
| P6-07 | `reports` + `blocks`; blocks honoured in search, ranking, offers | **DONE** — `reports` (category incl. first-class `off_platform`; not-self CHECK; feeds admin queue + `report.filed` outbox alert, never auto-penalises) + `blocks` (composite PK, not-self CHECK). `Block::partyIdsAround`/`existsBetween` honour a block **bidirectionally**; wired into **all three paths** — `ProviderSearch` (search + ranking) excludes blocked parties, `CreateDirectOffer` refuses (`PartyBlocked` 422). `BlockParty`/`UnblockParty`/`ReportParty`; `GET/POST/DELETE /v1/blocks`, `POST /v1/reports`; Filament report queue. OpenAPI + TS client. 6 tests incl. **block honoured in search (either direction) + offer refused** |
| P6-08 | Reviews: double-blind, 14-day window, simultaneous reveal | **DONE** — `reviews` (native `review_visibility`; UNIQUE(engagement, author); not-self + 1–5 CHECKs; `private_note` never published). `SubmitReview` rests each review `pending` — content withheld even from an API peek — until BOTH parties submit (revealed at once) or the shared window closes; the first submission fixes `window_closes_at`, the second inherits it. `RevealDueReviews` + `reviews:reveal` command publish a lone review when its 14-day window expires. `POST /v1/engagements/{engagement}/reviews`; public `GET /v1/providers/{party}/reviews` (published only). OpenAPI + TS client. Tests incl. **hidden-until-both, window-expiry reveal, dup 409, non-party 403** |
| P6-09 | Bayesian shrinkage rating display | **DONE** — `RatingCalculator` shrinks toward the prior mean (4.0, weight 10 pseudo-reviews): `(w·mean + Σ)/(w + n)`; recomputed into `provider_profiles.rating_avg` (shrunk) + `rating_count` (RAW, for P6-12's sample-size floor) on every publish. Test: **1×5★ → 4.09 ranks below 200×4.8 → 4.76; unrated shows null, not the bare prior** |
| P6-04 | Panic button + `safety_alerts` + emergency contact SMS + admin alert | **DONE** — `safety_alerts` (native `safety_alert_kind`; geo point; status open/acknowledged/resolved, resolution attributed to a named admin) + `emergency_contacts` (citext phone). `RaisePanicAlert` (one request) creates the alert, **texts every emergency contact directly** (not via the relay — a panic mustn't wait for a queue) and alerts staff via `safety.alert_raised` outbox — all server-side, so it **works with the app backgrounded**. New `SmsSender` rail (Fake/Log/**Twilio** since 2026-09-13, config-selected — mirrors the push rail; `TwilioSmsSenderTest` proves a fan-out survives one dead number); panic SMS copy through i18n (`sms.panic_alert`, per comms locale). `POST /v1/safety/panic`, `GET/POST/DELETE /v1/emergency-contacts`; Filament safety-alert queue (danger badge, acknowledge/resolve). OpenAPI + TS client. 5 tests incl. **all contacts texted + staff alerted + no-contacts still records** |
| P6-05 | Share-my-job signed expiring link | **DONE** — `engagement_shares` (opaque token stored **hashed**; expiring + revocable). `CreateEngagementShare` (participant-gated: customer or assigned worker; onsite/hybrid only via `supportsShareJob`, remote → 422) mints a link; a **public, tokenised Blade page** (`/s/{token}`) renders read-only, PII-minimised status — provider first name, approximate location (quarter/city), live status from `work_sessions` — a stale/revoked token is 404. `POST /v1/engagements/{engagement}/share`, `DELETE /v1/engagement-shares/{share}`; i18n `share.*` (parity OK); no-literal-colour + no-bare-string linters clean. 5 tests |
| P6-06 | Check-in-overdue watchdog | **DONE** — `RaiseOverdueCheckIns` + `safety:check-in-watchdog` command: an assignment past `scheduled_from` + grace with **no `work_session`** (never checked in) on an onsite/hybrid job raises a `check_in_overdue` `safety_alert` + `safety.alert_raised` outbox — deduped against an open alert, mode-gated via the policy. Reuses the P5-03 audit trail. 5 tests incl. **overdue-flagged, checked-in-not-flagged, remote-skipped, within-grace-skipped, dedupe** |
| P6-10 | Dispute flow + admin adjudication → balanced adjustment txn | **DONE** — `disputes` (category/status CHECKs; links `resolution_transaction_id` + `resolved_by_user_id`). `RaiseDispute` (party-gated; `dispute.raised` outbox, never auto-moves money); `AdjudicateDispute` — a human decision that, when it moves money, posts a **balanced `Adjustment` ledger transaction stamped with the admin's id** and referenced to the dispute (mirrors `ResolveReconciliationException`), else resolves with no ledger effect; writes `dispute.adjudicated` to the audit log; once-only. `POST /v1/engagements/{engagement}/disputes`, `GET /v1/disputes`; Filament dispute queue (adjudicate = resolve/reject + note). 5 tests incl. **balanced adjustment attributable to a named admin + no-money dismissal** |
| P6-11 | `warranties` + `warranty_claims` + **remedy job spawning** | **DONE** — `warranties` (one per engagement; duration/window CHECKs) + `warranty_claims`. `IssueWarranty` (provider); `FileWarrantyClaim` (customer) **spawns a REAL remedy job**: a job cloned from the original (`RMD-` ref, status engaged), its own engagement whose **origin is the warranty claim** (a third engagement origin — added `warranty_claim_id` + widened `engagements_origin_check`), agreed 0 (free), and a **real lead assignment to the original worker** — links `remedy_job_id`, sets warranty `claimed`, `warranty.claim_filed` outbox. `POST /v1/engagements/{engagement}/warranty`, `POST /v1/warranties/{warranty}/claims`. 5 tests incl. **claim → linked job + real assignment to original worker** |
| P6-12 | `provider_metrics` — 90-day rolling, sample-size floor ~5 | **DONE** — `ProviderMetrics` service: `jobs_completed_90d`, rating (from the cache), and an **on-time rate** (booked assignment whose work session ended ≤ `scheduled_to`) that is **returned null below the sample floor** (5) — "100% on-time (1 job)" is never displayed. Public `GET /v1/providers/{party}/metrics` returns only the display-safe subset (never the internal signals). Config-driven window/floor. Tests incl. **below-floor → null, at-floor → computed** |
| P6-13 | `repeat_customer_rate` surfaced to admin as a **leakage proxy** | **DONE** — same service computes the repeat rate `(completions − distinct customers) / completions` and a **leakage flag** = many completions (≥8) + low repeat (<15%) — a signal to look, **flagged never accused**, and never in the public metrics. A Filament `LeakageWatchWidget` (dashboard, admin-only) lists flagged providers with their completion count + repeat rate. Tests incl. **high-completion/low-repeat flagged, healthy-repeat not, below-threshold not** |

**Phase 6 (trust, safety, reputation) complete.**

### Phase 7 — Client follow-ups & lifecycle

| ID | Task | Status |
|---|---|---|
| P7-01 | `follow_ups` + `dedupe_key` UNIQUE + scheduler | **DONE** — `follow_ups` (native `followup_kind`/`channel`/`status`; UNIQUE `dedupe_key`; due partial index) + `comms_log`. `FollowUpScheduler::schedule` builds the key `{kind}:{anchor_type}:{anchor_id}:{sequence}` and is **idempotent via `firstOrCreate`** — the same at-least-once event 50× → exactly 1 row. `DispatchFollowUps` action + `follow-ups:dispatch` command sweep due `scheduled` rows. Test: **50× → 1** |
| P7-03 | `comms_log` + per-user per-channel budget | **DONE** — `CommsBudget` counts real sends in `comms_log` over rolling windows (config `followups.budget`: push 4/d, sms 2/d + 3/wk, whatsapp 3/d, email 2/d; in_app unlimited); over cap → `suppressed`, not sent. Test: **5 SMS → 2 sent, 3 suppressed** |
| P7-04 | Consent gate on non-transactional kinds | **DONE** — `FollowUpKind::requiresMarketingConsent()` (reengagement/maintenance_due) gated on the `marketing` grant via `ConsentState`; `isTransactional()` kinds (check_in_overdue/auto_approve_warning/payout_ready/…) bypass the budget entirely. Test: **revoke marketing → reengagement suppressed, check_in_overdue still sent** |
| P7-02 | Event-driven scheduling + cancellation | **DONE** — `FollowUpOrchestrator` subscribes to the outbox seam: `engagement.completed` → review_request (+2h) + review_reminder (+3d); `review.submitted` (by the customer) → cancels both by dedupe-prefix; `warranty.issued` → warranty_expiring 14d before expiry. New `CompleteEngagement` action (publishes `engagement.completed`, idempotent); `SubmitReview`/`IssueWarranty` now publish their events. `POST /v1/engagements/{engagement}/complete`. Test: **complete → 2 scheduled; review submitted → both cancelled; completing twice → still 2** |
| P7-07 | `quote_pending_customer` / `warranty_expiring` / `review_request` / `maintenance_due` + `response_action` | **DONE (all four named kinds, 2026-08-09)** — review_request/reminder, warranty_expiring and quote_pending_customer wired to events; **maintenance_due** now fires too, gated on a per-skill `maintenance_interval_days` that is null for most of the taxonomy (a wardrobe built once needs no servicing). Every follow-up carries a single **`response_action`** recorded via `POST /v1/follow-ups/{followUp}/respond` (target-gated, enum'd), `GET /v1/follow-ups` lists a user's nudges. Beyond the four named here, five more of doc 07's catalogue were also wired (job_unquoted, site_visit_reminder, job_starting_soon, awaiting_approval, payout_ready) — see the entry below; three remain unwired for stated reasons. Tests: response_action recorded → responded; non-target → 403 |
| P7-05 | WhatsApp Business API + approved templates + deep links | **DONE (code)** — `WhatsAppSender` rail (Fake/Log/**Meta**, config-selected); `MetaWhatsAppSender` speaks the Cloud API with ONE generic utility template per language (`handyman_follow_up`: title, body, URL button = follow-up id; doc 07 has the submission), copy from `followup.*` in the target's **comms locale**; the link lands on the app's `/follow-up/:id` which records `opened`. `FollowUpDelivery` routes each follow-up to the right transport at dispatch; a transport failure marks the row `failed`. Template approval in fr + en is the remaining external dependency. Tests: **`MetaWhatsAppSenderTest` (5), `follow-up.guard.spec.ts` (4), WhatsApp follow-up → transport got template + fr locale + deep link** |
| P7-06 | Channel ladder in_app → push → whatsapp → sms → email | **DONE** — `ChannelLadder::pick` chooses the outbound channel (push if a live device token, else WhatsApp — the workhorse), used by the orchestrator; the follow-up row is always the in-app record; SMS/email reserved (SMS transactional, email for receipts). Test: **ladder picks push with a token, WhatsApp without; push follow-up reaches the device token** |
| P7-08 | Provider CRM surface (customer list, pipeline, manual follow-up, do-not-contact) | **DONE (all four parts, 2026-08-09)** — the **pipeline** was the missing quarter and is now real: `ProviderPipeline` + `GET /v1/provider/pipeline` reports four stages off existing rows (offers awaiting an answer / quotes out / work in flight / completed in the window), each a count and a value. **Not a forecast** (nothing weighted by a probability of closing) and an **unpriced lead is counted but contributes no money** — falling back to the job budget where the customer named one, never inventing a figure. Client surface is the "My business" screen (Pipeline / Clients segment). 4 tests incl. unpriced-lead and cross-provider isolation. Backend below — `ProviderCustomers` builds the client book (per customer: job count, completions, lifetime value, last engagement) from the provider's engagements; `ScheduleManualFollowUp` lets a provider send a `reengagement` nudge on the **same budget + consent gates** (`created_by_user_id` recorded) — a provider can't spam through the platform; `do_not_contacts` (per provider→customer) is **honoured absolutely** — refused at schedule time and re-checked at dispatch. `GET /v1/provider/customers`, `POST /v1/provider/customers/{party}/follow-up`, `POST`/`DELETE .../do-not-contact`. 3 tests. (Pipeline view is a client/admin UI surface.) |

**Phase 7 complete: 8/8 tasks, all parts. P7-05's live WhatsApp template approval is the only
remainder, and it is external (credentials), not code.**

### Phase 8 — Growth and scale

| ID | Task | Status |
|---|---|---|
| P8-01 | Referrals: codes, qualify-on-first-completed-paid-job, ledger-backed | **DONE** — `referral_codes` + `referrals` (one per referee UNIQUE; not-self CHECK). `ReferralService`: `codeFor` mints a code; `claim` guards **self-referral + duplicate + unknown code**; `qualify` (on `engagement.completed` for the referee, via `QualifyReferralOnCompletion` listener) books a **ledger-backed reward** — DR platform_revenue / CR `promo_liability`[referrer] (`TxnKind::ReferralReward`) — a real liability, idempotent. `GET /v1/referral-code`, `POST /v1/referrals/claim`. 4 tests incl. **ledger-balanced reward + self/dup blocked** |
| P8-04 | Bidding mode behind a feature flag; off by default | **DONE** — `config/marketplace.php` flags (`dispatch_enabled`/`bidding_enabled`, both env-gated, off by default) surfaced in `GET /v1/meta` `features`. Test: **meta.features.bidding == false** |
| P8-05 | Rebooking a known provider in one tap | **DONE** — `RebookProvider` clones the customer's most recent job with the provider into a fresh open job and sends a direct offer (reuses `CreateJob`+`PublishJob`+`CreateDirectOffer`, so blocks still hold); refused if no prior engagement. `POST /v1/providers/{party}/rebook`. 2 tests |
| P8-02 | Referral fraud controls: velocity + review queue | **DONE** — a referrer over the **weekly velocity limit** (config) has new referrals **flagged for review** (not blocked, not auto-rewarded); `qualify` skips flagged referrals; an admin clears them via a Filament referral queue (`clearReview` → qualifies immediately if the referee already completed). Device-fingerprint dedupe is a further control (noted). 2 tests incl. **over-velocity flagged + flagged-not-auto-qualified-until-cleared** |
| P8-03 | Dispatch mode: ranking engine, fan-out, offer expiry cascade | **DONE** — `DispatchJob` ranks via `ProviderSearch` (skill + coverage + tier + rating) and **fans out to the top N** not-already-offered (config `dispatch_fanout`), honouring blocks; `RedispatchStaleJobs` + `dispatch:cascade` command **cascade to the next batch** when a dispatch job's offers all expire with no engagement. Behind the `dispatch` flag (P8-04). 2 tests incl. **fan-out to top-3 + cascade to next 3 on expiry** |
| P8-06 | Admin analytics: liquidity, match rate, time-to-offer, leakage | **DONE** — `MarketplaceAnalytics` computes over a rolling window: **liquidity** (offered rate), **match rate** (engaged/jobs), **avg time-to-first-offer**, active providers, and the **leakage-flagged** count; surfaced via a Filament `MarketplaceAnalyticsWidget` (stat cards) on the dashboard. 2 tests. Also fixed a float-binding-in-bigint-context bug in the shared leakage query (`?::numeric`). |

**Phase 8 (growth and scale) complete: 6/6.**

**Every build-plan task is done on the BACKEND** (as of 2026-08-09; the last genuinely unbuilt work
was P7-08's pipeline and six dead `FollowUpKind` cases — see the entries below). The client, native,
realtime and offline surfaces (P4-04..07, P5-01/02) and the public/SEO Blade pages have all landed.

> ⚠ **This paragraph used to say "backend and client", and that was wrong.** A 2026-08-13 sweep found
> the app never calls 43 of the API's 91 operations — no reviews, no panic button, no verification
> upload, a Withdraw button that requests nothing. See the "Open gap" section at the top. A task
> being done end-to-end on the server, with tests, says nothing about whether any screen reaches it.

> ⚠ **This paragraph was wrong for a while, and the reason is worth keeping.** It used to say the
> backend was complete when a quarter of P7-08 and seven follow-up kinds had never been written —
> they were *declared* (an enum case, a table, a doc row) and nothing invoked them. Hedged status
> markers like "DONE (backend)" and "DONE (core)" are where that hides. If you are auditing, check
> what **calls** a thing, not what declares it.

What remains is **not code**: external dependencies awaiting real credentials (CinetPay live
sandbox, FCM project, WhatsApp template approval, SMS), an iOS build needing macOS, and the
founder-owned legal items in doc 05's launch checklist.

## Design debt (tracked)

- **Design system: MODERNIST (claude.ai/design project "Modernist", id
  `53a2fd5a-4c0d-4bd7-a71b-a1a46608a3dc`), applied 2026-09-12.** Flat and architectural: one
  accent (#ec3013) on a warm off-white ground, everything in Archivo, no corner rounded anywhere,
  strong 2px dividers between regions, button labels flush left, nothing floats, photography in
  black and white. Its light theme is in `tokens/tokens.json` verbatim; the dark theme is ours,
  designed on the same warm ink. The design's own `readme.md` / `styles.css` are the reference —
  read them through DesignSync before changing a surface.
  - **Icons: Lucide, on all three surfaces (2026-09-12).** The app keeps its Ionicons NAMES —
    `mobile/src/app/core/icons.ts` registers a Lucide glyph under each (Ionicons checks its
    registry before fetching an SVG), so a new `<ion-icon name>` either maps there or falls
    through to the Ionicon with a console warning: **add the mapping**. The site's
    `public/partials/icon.blade.php` is generated from `lucide`'s node data (take nodes, don't
    draw). The admin uses `LucideIcon::*` (codewithdennis/filament-lucide-icons) at the resource
    and `App\Filament\LucideChrome` for Filament's own chrome aliases.
  - **`.grayscale` imagery: deliberately not applied.** The product has no marketing photography.
    Its only photographs are evidentiary — a deliverable, an ID document, a chat attachment — and
    colour is information in every one of them. If marketing photography is ever added it goes
    through the wrapper; the evidentiary ones never do.
- **UI quality bar (user-mandated): every UI must be beautiful, professional, perfect.** New UI is
  built to that bar from the start on the design-token system (light+dark, semantic colours,
  no-literal-colour lint).
  - **Filament admin — reworked to full visual fidelity (proposal approved), then found broken in a
    wide window on 2026-08-11 and fixed; see the top entry.** The dashboard is a
    single bespoke-view widget (`OverviewWidget` → `resources/views/filament/widgets/overview.blade.php`)
    that reproduces the approved mockup pixel-for-pixel inside the Filament shell: KPI cards with
    hand-drawn SVG sparklines (open jobs, active engagements, escrow held, GMV 30d, revenue, open
    reconciliation exceptions — the last highlighted), a "needs attention" reconciliation panel with
    severity stripes, a recent-engagements table (money + status pills + milestone progress), and a
    "money held" ledger breakdown. All computed live from the models + ledger (`Ledger::totalByKindMinor`).
    Its scoped CSS uses the token palette and syncs to Filament's `.dark` class for both themes. Brand
    + semantic palette, Inter, collapsible sidebar. The **Engagement detail** page is likewise a
    bespoke view (`filament.infolists.engagement`): header, money metrics (agreed/escrow-held/released/
    visit-credit), a milestone timeline, the workforce list, and key facts. **Job and Offer detail
    views** are likewise bespoke (`filament.infolists.job` / `.offer`). Shared token CSS lives in
    `filament.partials.hm-theme`, hardened for **extreme responsiveness** — fluid 3→2→1 grids,
    wrapping headers, tables that scroll in their own container, scaled type/padding at ≤560/≤380px;
    nothing overflows a phone.
  - **`DemoSeeder`** populates the dev DB with a coherent Cameroon marketplace whose money flows
    through the ledger (escrow funded, milestones released, credits, a payout, one reconciliation
    exception) + a loginable superadmin, so `/admin` is full when served. Run
    `php artisan db:seed --class=Database\Seeders\DemoSeeder`; log in at `/admin` with
    `admin@handyman.cm` / `password` (enrol 2FA once).
  - **Ionic app — customer + provider sections built, and reworked to the bar on 2026-08-13** (see the
    top "what was done" entry). Token-driven (light+dark, no-literal-colour + no-bare-string linters
    clean), one shared `app-empty-state` for every empty list, a legible disabled-button treatment
    including on brand-coloured cards, and a responsive shell with a side rail on web and a tab bar on
    phones. Density and the clipped category rail on Discover are fixed; rows hold their shape down to
    320px. **Judged from viewport-sized captures at 320 / 360 / 390 in both themes** against the live
    API — `ng build` green is not verification.
  - **Dead affordances found by looking rather than by grepping.** Discover's "change location",
    Jobs' two surplus "+" buttons and the account's "Add an address" all had no handler or no
    distinct purpose. All three are resolved (the last one by building the flow behind it — see the
    top entry). **The lesson worth keeping: a screenshot finds these and a grep does not**, because
    the markup for a live control and a dead one is identical.
  - **"Map" on discover: DECIDED — dropped** (founder decision, 2026-08-09). This product never
    exposes a provider's service area or coordinates (P2-03; the public provider resource carries no
    location at all), so there was no map of providers to show and the link had never done anything.
    The affordance and its i18n key are gone; customers find someone through search, categories and
    the ranked rail. Discover now has **zero dead links** (verified in a browser).
  - **Secure token storage: VERIFIED on device 2026-08-09** (see the entry below). The claim that
    had rested on the plugin's own guarantees since P5-01 is now demonstrated.
  - **Multipart on device: VERIFIED 2026-08-09** — it was broken too, and the fix carries it (below).
  - **Still owed:** an iOS build (never attempted — needs macOS/Xcode).
    The messages tab, the discover rail (search + category filters + "See all"), the provider
    profile, the **provider client book (P7-08)**, the public/SEO Blade pages, the realtime/media
    surfaces (P4-04..07), the offline layer (P5-02) and the PWA/Android build (P5-01) have all
    landed, and native networking on device now works (CapacitorHttp).

## API reachability: CLOSED — 0 of 93 operations uncalled (re-verified 2026-09-11)

`npm run check:uncalled` (`tools/find-uncalled-endpoints.mjs`) sweeps `openapi/openapi.yaml`
against every `.ts` file in `mobile/src/app` (`operationId` → does any client file contain the
literal templated path?). The check is sound: both `api.service.ts` and the offline write queue
address the API by its literal path, so a called endpoint always appears verbatim.

On 2026-08-13 this found **43 of 91** operations unreached — no reviews, no panic button, no
verification upload, a Withdraw button that requested nothing — while the tracker said "backend
and client complete". Same pattern the tracker already warns about — check what *calls* a thing,
not what declares it — at a scale the earlier audits missed because they audited status markers
rather than reachability. It was closed in three days of commits (2026-08-13 → 08-16): reviews,
safety (panic, emergency contacts, blocks, reports), verification, deliverable review, quote
revision, site visits, payouts, refunds, cash settlements, payment intents, follow-ups, referral
codes, rebooking, warranties, share links, media, disputes, and the rights pair (`DELETE /me`,
`GET /me/data-export`).

**Correctly absent** (6, declared in the script so the sweep stays honest): the payment webhook
(gateway → server), the two `/notes` reference-slice operations (P0-05), the two worker-assignment
operations (an org-dispatcher function the admin panel performs), and `GET /provider/credits`,
whose one number already rides in the `/provider/earnings` payload the only reader fetches.

This section stayed at "25 open" for almost a month after the last gap closed — the sweep ran
green, the tracker did not. Re-run it before believing this paragraph.

## What was done, most recent first

- **Fifteen advisories closed** (2026-09-13). `composer audit` had been reporting 15 across four
  packages, one of them on the admin's own door: Filament's app-MFA accepted a TOTP code after a
  newer one had been used (CVE-2026-84306), plus a password-validity disclosure on its login
  page; Guzzle (host-check bypass, cookie scope), commonmark (eight DoS/XSS), Livewire (DOM XSS).
  Updated to Filament 5.8.1, Livewire 4.4.4, Guzzle 7.15.5, commonmark 2.10.1 (framework
  13.20 → 13.31 came with them); audit clean. 557 tests, PHPStan and Pint green; the admin
  captured after the bump (dashboard, jobs, safety alerts at 1366px) still wears the Modernist
  skin — stale Filament font files from the previous publish removed.

- **A request id that exists, JSON logs, Sentry behind a switch, phones out of the logs**
  (2026-09-13, `RequestId` middleware, `Redact`, `sentry/sentry-laravel`). `Problem` had
  promised "`trace_id` — the id support can search in logs" since its first commit and minted a
  random UUID per response that appeared nowhere else: a person could read it off their screen
  and nobody could do anything with it. Now one id per request — honoured from a well-formed
  inbound `X-Request-Id` so a retry chain shares it, minted otherwise — is on the response
  header, on every log line the request wrote (Laravel `Context`, which Sentry attaches too)
  and in any problem+json's `trace_id`. Outermost middleware on every host.
  - Production logs are one JSON object per line (`LOG_STDERR_FORMATTER`), so `docker logs`
    is greppable by `request_id` and a shipper can index fields.
  - The Twilio and Meta adapters logged full phone numbers; `Redact::phone` (`+2376…111`) in
    every log context now — enough to match a ticket, not to call anyone (doc 04).
  - Sentry wired through `Integration::handles` for unhandled exceptions, inert without a DSN,
    PII off. Its servers are abroad: recorded as an open decision, not switched on.
  - Evidence: `RequestIdTest` (3: header = `trace_id` = the written log line's `request_id`;
    inbound id kept when well-formed and replaced when not; the site gets one too), redaction
    asserted in both sender suites.

- **Object storage in the stack, and one API field that leaked the bucket layout** (2026-09-13,
  `deploy/compose.yml` `minio` + `minio-init`, `deploy/minio/init.sh`). Uploads were on a local
  volume "for v1"; the ADR says self-managed MinIO in country, and doc 04 says identity papers in
  a SEPARATE bucket with SEPARATE credentials. Now: two buckets, two users each confined by
  policy to its own bucket (a leaked media key cannot read anyone's papers), `verification`
  versioned, nothing anonymous; the root user only administers and the app never holds it.
  `league/flysystem-aws-s3-v3` added; the env example carries every key for both disks.
  - `MediaResource` (job-report photos) returned `storage_path` — the object key — where the
    message and verification resources already refused to. It now returns `url`
    (`GET /media/{id}`, entitlement-checked) like the others; OpenAPI + the generated client
    follow. The app never read the path, so nothing on the phone changes.
  - Evidence: `ObjectStorageTest` (both disks resolve to the S3 adapter with different keys and
    buckets, path-style against `minio`; the API spec names no storage path or bucket host).
    No Docker on this machine: the live MinIO round trip is the first-deploy smoke.

- **FCM: the service-account exchange, and dead tokens cleared** (2026-09-13, `FcmAccessToken`,
  `FcmPushSender`). The push adapter expected a pre-obtained OAuth token from the environment —
  a token Google issues for one hour, so in production it would have worked for the first hour
  after deploy and then silently returned 0 for every push. Now the app holds the service-account
  JSON (`FCM_SERVICE_ACCOUNT_JSON`, base64, or a file), signs the RS256 JWT itself (no library
  for one claim set), exchanges it, caches the bearer 55 min, re-mints once on a 401 mid fan-out,
  and never throws when Google is down. A registration token FCM reports `UNREGISTERED` /
  `NOT_FOUND` is nulled on its device row in the one place that learns it, so `ChannelLadder`
  stops choosing push for that phone and falls through to WhatsApp instead of sending every
  nudge into a void.
  - Evidence: `FcmPushSenderTest` (5: the JWT's claims and signature verified against the public
    key, one exchange per fan-out, the FCM request shape; a gone token cleared and the live one
    kept; 401 → re-mint → retry; no token → nothing sent, nothing thrown; the provider builds
    from the env and rejects a service account without a private key). Test key in
    `tests/Fixtures` because PHP on Windows cannot mint one without an openssl.cnf.
    Never pushed to a real device — pends the Firebase project and a physical phone.

- **SMS through Twilio — and the sign-in code finally reaching a phone** (2026-09-13,
  `TwilioSmsSender`, `SmsOtpSender`). The SMS rail (`SmsSender`) had Fake and Log only. Worse,
  and found while wiring it: **the OTP had never had any sender but the log**. `AppServiceProvider`
  bound `OtpSender` to `LogOtpSender` unconditionally, in every environment — a deployed build
  would have taken the phone number, written the code to a file, and left every single person on
  the verify screen for ever. Nothing in the launch checklist would have caught it: the OTP tests
  read the code from the fake, and the dev flow reads it from `dev_code`.
  - `otp.sender` (`OTP_SENDER=log|sms`) now chooses; `sms` composes the general SMS rail, so the
    aggregator is picked once for OTPs, panic alerts and follow-ups. The OTP text is one line per
    language, unaccented (GSM-7 — accents halve a segment), French first to a stranger, one line
    to someone whose `comms_locale` is known; both lines together fit one 160-character segment.
  - `TwilioSmsSender`: Programmable Messaging, basic auth, `From` or `MessagingServiceSid` by the
    sender's shape; never throws — unreachable, a bad token, a landline (21614) / invalid (21211)
    / opted-out (21610) number are logged and dropped, the recipient-side codes at `info`.
  - Evidence: `TwilioSmsSenderTest` (4: form + auth + URL, sender kinds, the three failure modes
    never throw, a panic fan-out where one dead number does not stop the other);
    `OtpOverSmsTest` (3: the binding follows config, `RequestOtp` texts the code, the bilingual
    copy and its GSM-7 budget). Never sent a real text — pends a Twilio account and sender.
    The deploy example now says `OTP_SENDER=sms`, with the consequence of `log` spelled out.

- **WhatsApp through Meta's Cloud API** (2026-09-13, `MetaWhatsAppSender`, `WHATSAPP_SENDER=meta`).
  The workhorse channel had interfaces, a ladder and a `log` driver, and nothing that could reach
  a phone. Every follow-up is business-initiated, so every one is a template message; instead of
  sixteen kinds × two languages through Meta's human review, ONE generic utility template per
  language (`handyman_follow_up`: `{{1}}` title, `{{2}}` body, a URL button whose dynamic suffix
  is the follow-up id) carries the copy that already lives, bilingual and tested, in
  `lang/*/followup.php`. Best effort by contract: Meta down, a bad token, a number not on
  WhatsApp (131026, logged at `info` — a fact about the recipient) are logged and dropped, never
  thrown. The exact submission (fields, samples, button) is in doc 07 "The WhatsApp template".
  - Writing it found that **the deep link every nudge carried landed nowhere**: `APP_URL/follow-up/{id}`
    — the site host in production, where no route answers, and no app route either. Now one
    `FOLLOW_UP_LINK_BASE` (the APP host) feeds both the delivery layer and the button, and the app
    answers `/follow-up/:id` (`followUpGuard`): records the tap as `opened` — the number that
    justifies WhatsApp's per-message cost — then opens the job the nudge is about; every failure
    still opens the app.
  - Evidence: `MetaWhatsAppSenderTest` (5: payload shape against the Cloud API, per-kind override
    + en fallback, parameter sanitising, the three failure modes never throw, end to end through
    `FollowUpDelivery` with the app on its own host); `follow-up.guard.spec.ts` (4, Karma).
    Never spoken to Meta: needs a Business portfolio, a System User token, the template approved
    in fr + en — external, with a human queue; `log` until then.

- **The production stack, as code** (2026-09-13, `deploy/`, `docs/11-deployment.md`). Every
  launch-checklist item left needs a staging environment and there was no way to stand one up.
  One box, two hostnames (site/admin/API on `SITE_HOST`; the PWA + proxied API/websocket on
  `APP_HOST`, same-origin as `environment.prod.ts` expects), Caddy for TLS/HTTP3/compression, one
  PHP image for FPM + outbox + Horizon + scheduler + Reverb, PostGIS + Redis alongside.
  `deploy/deploy.sh --first` seeds roles + taxonomy and mints the first superadmin through the new
  `staff:bootstrap` (one-time password, 2FA enforced by the panel) — never `DemoSeeder`. No Docker
  on this machine, so **CI builds both images and smokes them** (`images` job).
  - Writing it found that **no command was scheduled** — twelve existed, `routes/console.php`
    had the framework's `inspire` and nothing else. In production no follow-up would ever send,
    no offer expire, no stuck payment resolve, no review reveal. `routes/console.php` now carries
    the schedule (`schedule:list` shows twelve entries); `outbox:relay` and `horizon` are daemons.
  - Honest gaps listed in doc 11: **SMS, WhatsApp and the FCM token exchange were missing** (all three landed
    the same day, above), CinetPay operator codes to confirm on the sandbox, object storage (in the stack
    since, above), no log shipping (JSON + request id since, above; a destination is a hosting
    decision).

- **Nothing ever moved a job past `engaged`** (2026-09-13). Found by writing the launch-checklist
  walk-through of a remote engagement end to end: the state machine defined scheduled → en_route →
  in_progress → work_submitted → completed from day one, and no action transitioned to ANY of
  them — the provider's signals were narrated into the thread ("the chat is the state machine")
  and the job's own `status`, which every list and the admin read, stayed `engaged` for ever. A
  finished job sat in the provider's active-work list and read "engaged" to its customer. The demo
  data hid it: `DemoSeeder` writes `in_progress`/`completed` directly.
  - `App\Domain\Jobs\JobProgress` is now the one translator from event to transition — it only
    uses the state machine, and it WALKS: a customer approving a job the provider never formally
    submitted steps through the unreported states rather than being refused. Wired into check-in
    (→ in_progress), status signals (on_the_way → en_route, started/resumed → in_progress,
    completed → work_submitted), the job report and deliverables (→ work_submitted), a rejected
    deliverable (→ in_progress), completion (→ completed), and disputes (→ disputed, settled to
    completed or in_progress by whether the work was finished). 7 unit tests + the walk-through.
  - Same walk found `WorkProgress::currentStatus` ordering by `created_at` alone — two signals in
    one second tied, and "arrived" vs "started" came back by luck of the plan. The UUID v7 id is
    the tie-break now.
  - **Launch checklist: 21 of 34 boxes now ticked with named evidence** (doc 05). The thirteen
    open are legal/payment-provider items, physical-device tests, socket-kill convergence, and
    the iOS build.

- **WCAG AA contrast as a CI gate** (2026-09-12, `62e3d26`) — launch-checklist item "both themes
  pass WCAG AA, verified independently". `npm run check:contrast` (`tools/check-contrast.mjs`)
  measures every token pair the surfaces draw, per theme, at the threshold its use demands. First
  run: **10 shortfalls, all light theme**; the dark theme designed the day before passed. Fixed by
  darkening four of our own light values (muted text, success/warning/info, the 2px rule) and by
  the rule the design itself states: **the accent is chrome-grade (3:1) — small accent text uses
  `brand-strong`** (26 template/CSS edits across app, site, admin). A new `brand.onInverse`
  (the ramp's light step) carries the kicker on the dark band. **One waiver, printed on every
  run: a label on the accent fill is 3.76:1** — see Open decisions.

- **Lucide on all three surfaces** (2026-09-12, `879a7f7`) — the design system's icon set, done
  the way each surface draws icons so no screen changed: the app registers a Lucide glyph under
  every Ionicons name in use (71 — Ionicons' registry beats its SVG fetch), the site's inline
  partial is regenerated from Lucide's node data, and the admin gets `LucideIcon::*` at the
  resources plus `LucideChrome` for the ~80 aliases Filament draws itself (sort arrows, chevrons,
  the eye on View, search, pagination, notifications). One harness note: PowerShell's
  `Get-Content -Raw` → `WriteAllText` round-trip turns em dashes into `â€”` — pass `-Encoding utf8`
  or edit with the Edit tool.

- **The Modernist system, on all three surfaces** (2026-09-12; four commits). The founder's new
  design is a claude.ai/design *design-system* project — tokens, type, components, a readme — not
  new screens, so it landed as a re-skin through the token layer plus the structure the tokens
  cannot reach.
  - **Tokens.** Palette from the design (light verbatim; dark designed to match), radius 0 at every
    step including the pill, `shadow.sm: none`, a `rule` group (1px/2px), and the typeface as a
    token: Archivo, **self-hosted** as one ~35kB variable latin woff2 with `font-display: swap`
    (the PWA has to render offline, and a font CDN is one more thing to wait for). The generator
    emits the @font-face per surface — root-relative for the app, because Angular's CSS pipeline
    resolves a relative `url()` against the importing file (global.scss), not the imported one.
  - **App.** Tab bar un-floated onto a 2px rule; every header ends in one, every footer begins with
    one; Discover's brand wash gone; cards are fills without a hairline (63 of them, stripped by
    script); segments are ruled boxes with the chosen option filled; outline buttons are ink in a
    rule; initials avatars are squares; dashed affordances solid. **Block-button labels are flush
    left by wrapping each in `<span class="min-w-0 flex-1 text-start">`** — the centring lives in
    Ionic's `.button-inner`, shadow DOM with no part, and a slotted element takes part in that flex
    layout. `::part(native)` does nothing here; the comment in `ui.scss` says why.
    `ion-searchbar.searchbar-md` needed naming explicitly — Ionic's md class outranks a bare element.
  - **Site.** Header is a bar on the ground with the rule, not a frosted pill; hero flat (wash and
    the four-percent mark gone, and the `--hm-mark` mask with them); cards fills, no lift; trust
    strip a ruled grid; sections separated by rules not whitespace; copy flush left; the closing
    banner is the **poster statement** — the one place red runs as a field. `edge-inverse` added to
    the Tailwind theme for rules on the inverted band (it was `white/15`).
  - **Admin.** Filament reads `var(--radius-*)` from a *layered* `:root`; one unlayered block in
    `admin.css` squares the whole panel. Sections/tables/stats drop ring and shadow; sidebar and
    topbar on the ground with the rule (the topbar's glass pseudo-element is gone; its Floating-UI
    caution stays as a comment). Font via `LocalFontProvider` with no URL — no CDN request.
  - **Verified** from 390px captures of the app (both sections, both themes), the site at 1280/390
    in both themes, and the admin at 1440 in both themes, against the live API. 492 backend tests,
    Pint, colour/string lints, uncalled-endpoint sweep all green.
  - **Harness notes worth keeping** (scratchpad `shot.mjs` / `admin-shot.mjs`, headless Chrome over
    raw CDP): the Bash tool **mangles backslashes inside heredocs**, so a Windows path or a `\n` in
    a heredoc'd Node script silently fails to match — write scripts to a file with forward slashes.
    Navigating to `/welcome` while signed in **revokes the token** (it signs out), so keep signed-out
    routes in their own run. `artisan serve` is single-threaded, so a page's parallel boot fetches
    queue behind each other — a screen "not loaded" after 3s is the harness, not the app.
  - `tools/dev-up.mjs` hung for ever after starting Postgres: pg_ctl hands its inherited stdout to
    the server and `spawnSync` never sees EOF. `stdio: 'ignore'` + `-w`.

- **"Informatique et réseaux à domicile", filed under Retouches.** Every card in the Discover rail
  named a trade that had nothing to do with its headline — home IT under clothing alterations, an
  air-conditioning firm under something else again — and a provider's profile listed a dozen
  unrelated trades spanning hairdressing, tiling and languages. It reads as a bug to everyone who
  sees it, which is most of what "the app looks unfinished" was pointing at.
  - Not a bug: `DemoCoverageSeeder::supply()` iterates the TRADES rather than the providers, on
    purpose, so that no page in the public taxonomy is a dead end. Coverage was chosen over
    coherence and the note says so. The cost had simply never been looked at from the app.
  - Both, now. Each demo provider gets its **signature trade seeded first** — keyed by headline, so
    it survives the provider list changing — and the directory's eager-load is **ordered by
    `created_at`** instead of leaving the displayed trade to whatever order Postgres returned. The
    earliest trade a provider listed is a fair proxy for their main one, and the client already
    shows the first (`customer.service.ts`, the `.find(Boolean)` over `p.skills`).
  - **The trade was also being truncated away.** That line is rating · trade with the trade as the
    half that shortens, so eighteen characters of "Pas encore de note" were spending the trade's
    room: "Installation de réseau" and "Installation de climatiseur" both arrived as
    "Installation d…". The rail says "Nouveau" / "New" and spells it out on the profile, where
    there is space. Every trade now reads in full.
  - Provider cards were also packed at `space-sm` with an avatar, a two-line name, a meta line and
    a call to action pressed against the edges.

- **Create/Edit Provider Profile were empty pages with a Save button**, and both surfaces were
  given a top.
  - The two write forms in the admin panel shipped as the Filament scaffold — `->components([])`
    with a `//` inside — so they opened on nothing. What staff may change is deliberately narrow,
    because a provider profile carries three kinds of field and only one belongs in a form:
    **written by the provider** (headline, bio, accepted channels — editable, because moderation is
    a real job and a headline can carry a phone number or an insult); **earned** (rating, jobs
    completed — derived, the column is a cache, shown but never editable); and **granted**
    (verification tier — the fact that gates paid on-site work, raised by APPROVING a document,
    which records who approved what and when; a number typed here would grant the same capability
    with no trail). `party_id` is selectable on create and locked on edit — the column is UNIQUE and
    moving a profile would hand someone another provider's history.
  - **The public site had no top.** Every section sat on the same near-white surface, so the first
    screen — the only one most visitors read — was typographically strong and visually anonymous.
    It now carries a brand wash that fades downward, the header's own tool mark held at four percent
    as a CSS **mask** (so it takes its colour from the token rather than carrying a second copy of
    the palette in a data URI), and a hairline where the wash ends. The mark sits top-right, on the
    same side as the radial wash; behind the headline, where it started, it read as a smudge across
    the first line, and it is hidden below 48rem where it would fight the text for the same pixels.
  - **The app got the same top**, so the two surfaces read as one product rather than two things
    sharing a logo. On `ion-content` rather than `.page`, which is capped at 720px and would have
    left a tinted column floating in grey; the toolbar takes the same colour at full strength so
    header and content join with no seam.

- **The app opened on five jobs that were not yours.** Found while adding loading states: the
  `jobs` signal was seeded with five fabricated jobs — real-looking references, provider names and
  amounts ("Fuite sous l'évier · Atelier Nkeng · 900 000 FCFA") — and `chats` with three invented
  conversations, complete with an unread badge of 2. `loadJobs()` only assigned on a successful
  read, so a customer on a slow network, or one whose fetch failed, was shown four other people's
  jobs and a sum of money as their own, indefinitely.
  - The rule was already written in this codebase, one signal down, where the addresses list had
    been emptied for exactly this reason: **"illustrative content is a demo aid, a claim about the
    user is not."** Jobs, money and an inbox are the strongest claims the app makes about anyone.
    Both start empty now.
  - **That is what made loading states necessary rather than decorative**: with the fixtures gone,
    "You have no jobs yet" flashes at someone who has four. So `jobsLoaded` distinguishes "still
    asking" from "you have none" — a distinction an empty array cannot carry — and both lists draw
    the SHAPE of what is coming (`.hm-skel`, a swept placeholder that respects
    `prefers-reduced-motion`). On the networks this product is built for, that wait is long enough
    to be worth drawing, and it is the only option that does not move the content when it lands.
  - Verified all three states at 390px: signed out gives the honest empty state, the held loading
    state draws job cards and chat rows, and a real session fills them with Jean Mbarga's ACTUAL
    jobs — `JOB-GWRLP` and `JOB-IPTP4`, not the `JOB-7K2M9` the fixture invented.
  - Left as fixtures deliberately: the Discover provider list and the category rail. Those are
    illustrative marketplace content rather than claims about the user, which is the line the rule
    draws. Noted rather than assumed — a provider fixture that cannot be opened is still worth
    revisiting.

- **The other two UIs.** "Rework the UIs" meant all three surfaces, and the first pass had only
  touched the Ionic app. The public site and the Filament admin were reviewed the same way.
  - **Public site**: in good shape — the hero, the three-step explainer, the dark professionals
    section and the trades directory all hold up at 1280px and at 390px. One defect: the sticky
    header was `surface.base` at 88% with a 10px blur, and the 3rem/800-weight display headings slid
    underneath and stayed legible THROUGH it, smearing across the nav. 96% and an 18px blur — glass
    has to obscure what is behind it or it is just a tint.
  - **Admin — the brand name was wrong.** `->brandName('Handy-Man')`, hyphenated, in the topbar, the
    login card and every page title. It is "HandyMan" everywhere else. The sort of thing only ever
    seen by the people least likely to report it.
  - **Admin — the palette was the one surface the token generator did not reach.** Five hex values
    copied by hand into `AdminPanelProvider`, because Filament builds its ramps in PHP at boot and
    cannot read a CSS variable the way Blade, Tailwind and Ionic all do. `tokens/build.mjs` now emits
    `backend/config/tokens.php` and the provider reads it — the generated values matched the
    hand-copied ones exactly, so this changes nothing visually and removes the only place the brand
    could silently drift.
  - **Not a defect, checked properly**: the admin's mint primary button. `Color::hex()` keeps only
    the HUE and imposes its own lightness/chroma ramp, so the brand green never appears literally —
    and the button deliberately pairs shade 400 with 950 text. That is Filament v4's own soft-button
    language, and forcing the app's deep-green/white treatment onto it would break the pairing.
  - **Admin dashboard, three real layout defects.** Every job reference arrived broken across two
    lines ("JOB-" above "GWRLP") — a hyphen is a licence to break and had to be revoked, exactly as
    the money column already does. Five columns inside 520px set "Entretien de climatiseur" and
    "Douala Cool Services" one word per line. And `.hm-body` meant two different things in one
    stylesheet — a long report body, where `pre-wrap`/`overflow-wrap:anywhere` is right, and the
    exception card's text block, where it broke "mismatch" mid-word and held the line to 64ch inside
    a 300px aside. The exception amount now carries the same `nowrap` the table figures do, and the
    two-column grid gets `min-width:0` so the table scrolls inside its own card instead of starving
    the panel beside it.
  - Backend 470 tests green, PHPStan L6 clean, Pint clean.

- **The screen sweep at 390px, and the dark theme's first look ever.** Every screen in both sections
  rendered at real phone width, three at a time, signed in as a customer and then as a full provider.
  - **A type scale** (`tokens/tokens.json` → `text`/`leading`/`tracking`, eight steps). The app had
    twenty-five font sizes — 0.76, 0.78, 0.8, 0.82, 0.85, 0.88, 0.9, 0.92, 0.95rem — because every
    screen picked its own, and sizes a hair apart read as no decision at all. 281 body declarations
    across 29 stylesheets normalised onto five steps; anything above 1.06rem is a heading or an icon
    and stays hand-tuned. The document baseline and the page-title treatment are global now instead
    of restated at the top of a dozen page stylesheets.
  - **Copy caught by rendering it, not by reading it**: "Suivre le système" cannot fit a three-up
    segment beside "Clair" and "Sombre" (it is "Système" — the verb was redundant); and
    `provider.building` shipped as "En cours" in French, which on the provider's own dashboard sat
    directly beside a job status pill also reading "En cours", meaning something entirely different.
    It is "Trop tôt" / "Too soon" — and English "Building" was worse than ambiguous on a handyman
    marketplace, where it reads as the trade.
  - `provider.building` was doing two jobs: a stat value ("On-time: Too soon", which works) and the
    rating placeholder under the provider's own name, where standing alone it says nothing. The
    provider's profile now shows what a CUSTOMER is shown for the same fact ("Pas encore de note"),
    in muted grey rather than amber — having no rating yet is a neutral fact about someone new, not
    a warning about them.
  - **Dark mode holds up.** The independently-designed dark palette works across Discover, jobs,
    the empty states and the brand-coloured wallet card. One observation, deliberately not changed:
    the wallet's "Retirer" button fills with `brand.onPrimary`, which is white in light and near-black
    in dark — legible either way, but in dark it reads as an inset hole in a bright card rather than
    the raised primary action. Changing it means changing what `onPrimary` means, so it is a decision
    for the palette rather than a patch on one button.
  - The component style budget failed on files that did not grow: `var(--hm-text-md)` is simply
    longer than `0.9rem`. Raised 5/6kb → 7/9kb; it passes with no warnings at all at the new figure.
  - Not changed, considered: job detail's toolbar shows the reference while the body `h1` carries the
    title. Unlike the jobs LIST — where the code was the bold primary line and the title was greyed
    underneath — nothing here is inverted, so it was left alone.

- **"UIs look horrible" — and at 390px they did.** Every review up to here was done at 1045–1280px,
  which is the width a phone-first app looks worst at and the width nobody uses it at. Rendering the
  screens in 390px iframes (an iframe gets its own viewport, so media queries resolve honestly) made
  the causes obvious, and most were framework defaults left untouched rather than anything designed.
  - **Material UPPERCASES every button label.** "Send code" shipped as "SEND CODE", "Quote" as
    "QUOTE" — a screen of them reads as a row of warnings, and it destroys the word shapes people
    read by. Sentence case everywhere (`ion-button`, segment buttons, back buttons, alert buttons),
    with weight carrying the emphasis. Single biggest change on this list.
  - **Cards had a shadow scale in the tokens and used none of it.** `surface.raised` sits barely a
    step above `surface.base` in light mode, so a hairline border was doing all the separating and
    every screen read as flat boxes on flat paper. `.hm-card` takes `shadow-sm`; the border stays
    for dark mode, where a shadow has nothing to fall on.
  - **Segments were a grey slab with a 2px underline** — Material's tab-bar treatment on what is
    actually one control holding one value. Now a raised pill riding in a sunken track.
  - **The search field** shipped as a grey slab with no edge; it gets the same raised surface and
    hairline as the cards, so it reads as somewhere to type.
  - **The jobs list led with `JOB-7K2M9` and put "Fuite sous l'évier" underneath it in grey.**
    Nobody scans their own jobs looking for the reference. Title leads; the code sits under it in
    tabular figures, still there for reading out to support.
  - **The inline empty state pushed its button out through the side of its card at 390px.** It had a
    stacking rule at `max-width: 380px` — a breakpoint set just below the commonest phone width
    there is, and keyed on the viewport when the component sits inside cards of different widths.
    It wraps on available space now, with no breakpoint at all.
  - **A regression from the locale fix, caught by rendering it**: the taxonomy refetch was keyed off
    a before/after comparison inside `loadMe()`, and once the account's language was also adopted at
    launch, whichever got there first left the other seeing no change — so the Discover rail sat in
    English above French chrome. The language is watched directly now; it does not matter who moves
    it. Verified: the rail reads "Climatisation et froid", "Coiffure et beauté".
  - Left alone deliberately: the category tiles already reserve two lines (`min-height: 2.4em`) so
    the rail's baseline holds, and truncating trade names would be worse than a second line.

- **Erasure and data export were unreachable — the one uncalled pair that is a legal obligation.**
  `DELETE /me` and `GET /me/data-export` (P1-10) have existed since Phase 1, with tests, and no
  screen led to either. A right nobody can exercise is not a right.
  - New screen at `/privacy`, outside both shells like `/safety` and linked from the customer
    account page and the provider profile: a provider has exactly the same rights as a customer,
    and putting this under the customer tabs would have made it a customer feature.
  - **The export is shown, not hidden behind a download.** Seeing what is held about you IS the
    right of access; the file is the portability half and sits under it. Sections are rendered from
    whatever the server sends — repeated records (addresses, consents, devices) are counted on
    screen and spelled out in full in the saved copy, because a list of every device you ever
    signed in on is noise on a phone and is exactly what a file is for.
  - Field and section names are translated with a **fallback to the server's own key**, so a field
    added to the export later shows up as `some_new_field` rather than being dropped or printed as
    `rights.f.some_new_field`. An export that quietly omits a field is not an export.
  - **Saving works differently in the two places and says which one it did.** A browser saves from
    an object URL; a Capacitor WebView silently swallows that same click, and saving to the device
    filesystem needs a native plugin the app does not carry. Native copies to the clipboard instead
    and the toast names the action — "Saved" vs "Copied" — rather than claiming a file that is not
    there.
  - **Erasure is confirmed by typing the word, not tapping a button** — the panic button is held
    rather than tapped for the same reason pointed the other way. The copy says what is destroyed,
    that the financial record survives without saying who you are, and that there is no way back.
    On success the session is cleared locally (the server already deleted every token with the rest
    of the PII) and the app returns to Welcome.
  - Three defects found by rendering it: an absent provider profile printed **"PROVIDER_PROFILE /
    null"** (empty sections now say so out loud — "we hold none of this" and "we did not show you
    this" are very different answers on this screen); the language fields showed the stored codes
    **`fr` and `active`** rather than words; and every label resolved in TypeScript **froze in the
    language that was current when the page was built**, so on a cold load the chrome came up
    French and every row under it stayed English. Rebuilt on `onLangChange` — the first screen here
    that renders server-supplied field names, so the first that needed it.
  - Verified against the live API in French: the export renders for a populated account and for a
    brand-new one (empty sections and the singular both correct), the save produces
    `handyman-data-<date>.json` from a blob, and erasure through the app's own service leaves the
    user `closed`, the email null, the party tombstoned and the data key destroyed. 27 → 25
    uncalled operations, of 92. Backend 470 tests green.
  - **Found, not fixed:** `ErasePartyData` writes the display name `'Utilisateur supprimé'` — a
    French string in the database, shown to English readers too. Same class as the milestone titles
    (rule #11), but `display_name` is read by many resources, so the `title_key` treatment is its
    own task rather than a rider on this one.

- **The emergency screen spoke English to a French account.** Found by cold-loading `/safety`
  during the block/report pass: the app adopts the ACCOUNT's language in `CustomerService.loadMe()`
  and nowhere else, so it happened in the customer section only. `/safety` is outside both shells
  on purpose (a customer letting a stranger in and a worker walking into an unknown site need the
  same screen), and the whole provider section is outside that one too — both showed whatever the
  DEVICE spoke. Reached by a deep link, a push, or the route a packaged app restores on launch, and
  worst on the one screen read in a hurry.
  - `LocaleService.adoptAccountLocale()`, called from `AppComponent` after `init()`, so it runs for
    every section and no shell owns it. The remembered `me` answers first — a launch with no network
    still speaks the right language and costs no request — and only a device that has never cached
    one asks the server. The customer section still refetches and reconciles moments later, which
    remains what corrects a locale changed on another device.
  - Verified both paths on `/safety`: with a cached `me`, and again after wiping the read-through
    cache so the account had to be fetched. French either way, chrome and blocked list together.

- **You could be matched with someone you never wanted to see again.** `/blocks` ×3 and `/reports`
  were uncalled (P6-07). The server has honoured a block in search, in the public directory and at
  offer creation since Phase 6 — bidirectionally — and no one could place one. Reporting had the
  same shape: a `reports` table an admin queue reads, that nothing could write to.
  - Both live behind an **overflow on the provider profile**, not on the page. Almost everyone
    looking at a profile is deciding whether to hire; the two people who need this need to find it,
    not to be offered it.
  - **Reporting** is a category and their own words. The seven categories are chips rather than a
    select — one of them is `off_platform`, which is the thing the marketplace most needs told, and
    a dropdown hides what the platform will act on. The note under it promises exactly what the
    server does: a person reads it, nobody is penalised automatically, and the reported party is
    never told who reported them.
  - **Blocking** confirms first and the dialog says what it costs — never matched again, in either
    direction — and that Safety can undo it. A boundary nobody can find their way back out of is one
    people are afraid to use. On success it leaves the profile: a page you have just blocked, with a
    "request a quote" button the server would now refuse, is not one to be left sitting on.
  - Lifting a block is **not** confirmed. Unblocking is the reversible direction, and the same
    person can be blocked again in two taps.
  - **`GET /blocks` returned bare UUIDs.** "Unblock this id" is not a decision a person can make, so
    the row now carries `blocked_label` — the provider headline where there is one, the display name
    otherwise, and never more than that (P2-03). Eager-loaded, newest first; 3 tests.
  - Three defects found by rendering it. **`role: 'destructive'` does nothing on Material** — Ionic
    ships that colour for iOS only — so "Block" read exactly like "Report" and "Cancel", three
    identical dark lines, one of which cuts a person off; it wears the danger token now (in
    `ui.scss`, beside the disabled-button rule, which is the same class of Ionic defect). The
    overflow's `[attr.aria-label]` on an `ion-button` threw `onAriaChanged` on **every** visit to
    the profile — the exact trap `.sr-only` was written for, now using it. And the report sheet
    shared the page's `sending` flag with the offer button; it has its own.
  - Verified end to end against the live API in French: a report lands as `fraud`/`open` with the
    body verbatim, a block lands and the profile falls back to Discover, both blocked rows read as
    headlines on Safety, and unblocking drops the row and the database row with it. 27 uncalled
    operations, of 92 — down from 31 when the sweep was written.
  - **Found here, fixed in the entry above:** `/safety` is deliberately outside both shells, and locale
    reconciliation lives in `CustomerService.loadMe()`. Cold-starting onto the emergency screen —
    a deep link, a push, a restored route — renders it in English for a French account. The
    provider section has the same hole.

- **The alarm was reachable only through the API.** `POST /safety/panic` and all three
  `/emergency-contacts` operations were uncalled — on the one feature where that gap matters most,
  in a product whose whole premise is strangers entering each other's homes.
  - New screen at `/safety`, linked from the customer account page and the provider profile, and
    deliberately outside both shells: the person letting someone in and the worker walking into an
    unknown site need the same thing, and it belongs to neither role.
  - **Held, not tapped, and not confirmed.** A confirmation dialog in an emergency is one more
    thing to read and dismiss with shaking hands; a bare tap fires from a pocket and wakes staff.
    A 1.5-second hold answers both, with a progress ring so the wait is visible and lifting early
    visibly undoes it. Verified: a 400 ms press raises nothing, a full hold raises a `201`.
  - The location attempt is bounded at four seconds and never blocks the alert. A late alert is the
    failure mode that matters, so it goes without a fix and says which of the two happened.
  - Nothing here is queued offline. An alert that arrives when the network returns is not a safety
    feature, and the copy says plainly that it did not send rather than implying it will.
  - Emergency contacts: list, add (E.164 validated client-side so a typo is a sentence rather than
    a 422), remove. The screen states what those numbers are for and that nobody else on the
    platform can see them.
  - Two things caught by rendering it: the button's label ran out of both sides of the circle at
    every width, and the empty state carried a second "Add a contact" button an inch below the
    first, squeezing the sentence that explains why you would press either.

- **A provider could price a job and nobody could see it.** The hole in the middle of the
  marketplace: `POST /quotations/{q}/accept` was uncalled, and the reason turned out to be upstream
  of the app — **there was no way to READ a quotation at all.** A quote arrives before any
  engagement, so there is no conversation for the server to narrate it into either. It existed in
  the database and appeared nowhere.
  - New endpoint **`GET /jobs/{job}/quotations`** (spec, controller, resource, 5 tests): the job's
    customer sees every SUBMITTED quote — a draft is the provider's private working copy and is
    never disclosed — each with its lines and the quoting provider as a pre-engagement viewer may
    see them (headline and badge, never a name, P2-03). A provider sees only their own, so the field
    cannot be read off the endpoint. A stranger gets 403, not an empty list: "no quotes" and "not
    your job" are different answers.
  - The customer's job page now carries a **Quotes received** section — lines, total, deposit,
    balance, the provider's own note — and accepting confirms first with both figures said out loud.
    It is the one action there that commits to a price and a person and cannot be undone from that
    screen. A refusal shows the server's own words: a 409 means the quote lapsed or another one
    already formed the engagement, which is the single fact the customer needs.
  - The workspace's quote card had **two buttons wired to nothing** — "Accept & pay" and a "Counter"
    the product has no endpoint for. Replaced with one that opens the job overview, where every
    quote can be compared and the money is stated before it moves.
  - **Milestone titles were English strings in the database**, so a French customer accepted a quote
    and got "Deposit" and "Balance" back on their money screen. `GeneratedMilestone` now ties the
    generator and the resource together: the server emits a `title_key` for a title IT generated and
    null for one a person wrote, and the client says the former in the reader's language and shows
    the latter exactly as written (rule #11).
  - **`DemoCoverageSeeder` now seeds live quotes**, two on one job and one on another, at different
    prices with different deposit structures — the demo had no job in the one state the marketplace
    turns on, a customer holding a price and deciding. Quotes come from a provider who actually
    offers that trade where one exists.
  - **The suite had a security test reading the developer's `.env`.** Local OTP limits were raised
    to a thousand so a morning of clicking is not spent locked out, and `phpunit.xml` did not pin
    them — so "rejects the 4th OTP request for a phone within an hour" quietly stopped testing
    anything. The shipped limits are pinned in `phpunit.xml` now.
  - Verified end to end in a browser: two quotes render and compare at 390/360 in both themes,
    accept → 201, the engagement forms, escrow shows the full agreed amount, and the milestone plan
    comes back as "Acompte / Solde". 36 → 35 uncalled operations, of 92.

- **Withdraw showed a success toast and requested nothing.** The most dishonest thing a money
  screen can do, and invisible from inside the app: the payout history beneath it was fixture data
  that already looked populated, so nothing about the screen suggested the button was inert.
  - It opens a sheet now and posts `POST /provider/payouts`: how much (prefilled with the whole
    balance, with an "all of it" shortcut) and the wallet it lands in (prefilled with the account's
    own number, editable — paying out to a different number is legitimate and doing it silently is
    not). The sheet says what the request actually does before it is made: the money is reserved
    immediately, and only leaves the platform's books when the operator confirms.
  - Not queued offline, deliberately: a payout is a claim on a balance only the server knows, and
    replaying one hours later against a balance that has moved is how a provider ends up with two
    requests they made once. The idempotency key protects a retry, not a stale request.
  - `GET /provider/credits` is now marked correctly-absent rather than closed: `/provider/earnings`
    already carries `lead_credits`, and the earnings screen is the only place it is shown. The lead
    credit balance is displayed from that payload instead of a second round trip.
  - Two copy defects found by rendering it: `pro.payout_pending` interpolated the amount and the
    template appended the currency AFTER the whole sentence — "Retrait de 25 000 en cours FCFA" in
    both languages; and payout dates used a bare `toLocaleDateString()`, which takes the BROWSER's
    locale and printed "8/14/2026" in a French list. The currency is a parameter now, and dates
    follow the app's chosen language.
  - Verified against the live API: 201, the balance drops by the amount, the reserved figure rises
    by it, and the new payout appears in the history as pending. 37 → 36 uncalled operations.

- **The VERIFY button now goes somewhere, and the tier it moves is the one that gates paid work.**
  `/verification-documents` ×2 were uncalled, which meant a provider could be told they were not
  verified and given no way inside the product to become verified — the fact model refuses on-site
  paid work below tier 2 (P0-17/P6-03), so this was a closed loop with no door.
  - New screen at `/verification` (`provider/verification/`): the tier ladder saying what each rung
    *opens* rather than what it is called, then a row per document kind carrying the status of the
    caller's latest attempt at it, and the reviewer's own words when one was refused.
  - The kind is sent, never a tier — `DocKind::grantsTier()` decides server-side, so mislabelling a
    selfie as a licence buys nothing. An approved row loses its button: re-sending it can only
    lengthen the reviewer's queue.
  - **`id_verification` consent is recorded at the moment of upload** (P1-05), in the locale the
    wording was read in. That purpose existed in the API and nothing had ever written it, which left
    the consent covering the most sensitive data this product holds as the only one with no record.
  - Not queued offline, deliberately: the write queue replays a JSON body, and a 10 MB photo has no
    business sitting in local storage waiting for a network.
  - Verified end to end in a browser at 390/360/1200 in both themes: send → `201`, the row flips to
    "Being reviewed", `ReviewVerificationDocument::approve` → the profile rises to **tier 3** and the
    screen re-renders the ladder fully lit. 37 of 91 operations still uncalled.

- **Made the product testable locally, and wrote the guide for it** (`docs/LOCAL_TESTING.md`).
  - `npm run dev` / `npm run dev:fresh` bring the whole stack up in the one order that works —
    start the portable Postgres, migrate, serve the API on :8100 and the app on :4200 with ng
    serve's DEVELOPMENT configuration. It refuses to start a second server on a taken port, because
    the failure mode otherwise is a browser talking to yesterday's build.
  - **Signing in no longer means grepping a log.** With `APP_ENV=local` the OTP response carries
    `dev_code` and the verify screen shows it as a tagged dev chip that fills the boxes on tap. The
    gate is `app()->environment('local')` — not a config flag or a header — and two tests hold it:
    the suite runs as `testing` and asserts the key is absent, a second forces `local` and asserts
    the code returned is the one that actually verifies.
  - **`dev:fresh` did not work when first written**, which is worth keeping: `migrate:fresh` drops
    tables but leaves Postgres's native enum types, so the first migration died on `type
    "party_kind" already exists`. It wipes with `db:wipe --drop-types` now. A fully-qualified
    seeder class name also arrives from a Windows shell with its backslashes doubled.
  - Local OTP limits raised in `.env` (the IP limit is 10/hour, which a morning of testing
    exhausts); production defaults in `config/otp.php` untouched.
  - **The demo data spoke Latin**: every seeded job was "Climatiseur — autem vitae", from
    `fake()->words(2, true)`. Titles now end in what a customer would write.

- **Two more uncalled endpoints closed, and they were one loop**: an engagement could be started
  but never finished, and finishing is what opens the review window — so reviews were unreachable
  even in principle. The blocker was upstream of the screens: a job's embedded engagement summary
  carried **no engagement id**, so the app could not address the thing that completing, reviewing
  and disputing are all scoped to. Added `id`, `completed_at` and `viewer_has_reviewed` — the last
  deliberately about the viewer alone, since a flag that flipped when the OTHER party reviewed
  would leak what double-blind withholds (P6-08). 39 of 91 operations still uncalled.

- **Nobody could save an address, so nobody could post an on-site job.** `POST /addresses` existed
  and was never called; "Add an address" on the account page had no click handler; the new-job form
  could only pick from a list nothing could add to. On-site and hybrid jobs require an `address_id`
  — a DB CHECK (P2-01), not just validation — so **the app's central flow was impossible for a real
  user**, and two fixture addresses (`a1` Domicile, `a2` Bureau, ids that exist nowhere on the
  server) kept the picker looking populated. New `/new-address`, reachable from the account page and
  from the job form itself with a `returnTo` so the half-written request survives. Coordinates come
  from the device deliberately: `addresses.point` is a `geography(Point,4326)` behind a GIST index
  and matching is an ST_DWithin against it, so a typed street with no point is an address no
  provider can be matched to — with no geocoder and no map in this product, the honest options are
  to ask the device or to refuse, and a refusal says what it would cost rather than storing a
  centre-of-city guess. location_tracking consent (P1-05) is recorded right after the sentence that
  explains it.
  - **`me.name` fell back to the phone number AND the server seeds `display_name` with the phone**
    for an OTP-created user, so the account header printed the same number twice and `initialsOf()`
    was taking initials from a phone. Normalised to empty at the boundary — a phone is not a name.
  - A job with no budget rendered **"0 FCFA"**, which reads as "worth nothing" rather than "no
    figure named"; it says *Quote requested* now (the same rule P6-09 already applies to ratings),
    and an unassigned job says *Awaiting a provider* instead of leaving the row half empty.
  - **Acceptance, verified end to end in a browser with an overridden GPS fix**: a brand-new phone
    number signs up → the job form's empty address state → save an address (consent 201, address
    201) → back to the form → category, trade, address → `POST /jobs` 201 + `publish` 200 → the
    Jobs tab lists **JOB-8WC62 · Fuite sous evier · Ouvert · Devis demandé**. That path did not
    exist before this session.
  - Note for future runs: the OTP limiter is **10/hour per IP** (P1-02), which a scripted session
    exhausts quickly; `php artisan cache:clear` resets it in dev.

- **The Ionic app, looked at screen by screen in a real browser — the last open piece of the founder
  feedback. Four visual defects, two dead affordances, one whole missing flow, and a backend bug the
  test suite was structurally unable to see.** Verified over CDP at 320 / 360 / 390px in both themes
  against the running API, not from `ng build` green (see [[ui-quality-bar]]'s standing rule).
  - **Every empty screen was a grey line stranded above a screen of nothing.** Eight lists ended in
    `<p class="hm-empty">` — one muted sentence pinned to the top of a full-height scroll area with a
    screen and a half of black under it and no way forward, which reads as a screen that failed to
    load. One `app-empty-state` (`core/ui/`) now carries all of them: icon on a tinted disc, what
    belongs here, one line on how it gets here, at most one action. Two shapes — `full` claims the
    leftover height via `.hm-page-fill` (whose `min-height: 100%` resolves against Ionic's scroll
    container) and centres; `inline` stays compact when one section of a populated page is empty.
    An empty search and an empty account are now different states with different fixes.
  - **Jobs had three "+" buttons** (toolbar icon, a button, and a FAB) all calling `postRequest()`.
    One affordance per state now: the FAB when there are jobs, the empty state's button when not.
  - **A disabled button vanished instead of looking inert.** Ionic drops the whole element to
    `opacity: .5` — fill and label together — so WITHDRAW on the green wallet card was green on green
    on green, and POST REQUEST was dark on dark. Disabled solid buttons keep full opacity and say
    "off" with colour (muted on sunken); `.hm-on-brand` restates that in a brand card's own palette.
    **The page-level rule was out-specifying the shared one** — Angular's emulated encapsulation adds
    an attribute selector to every component rule — so `.withdraw` is scoped to `:not(.button-disabled)`.
  - **Discover claimed a location it never had.** The toolbar carried a location pin over the app's
    own name with "change" underneath, wired to nothing: a chip asserting HandyMan is where you are.
    There is no location picker and search is not location-filtered, so it is a brand lockup now.
    The category rail cut its last tile mid-word (it snaps and fades at the trailing edge now, and
    tiles reserve two label lines so the rail's baseline stops wandering), and provider rows were
    five solid brand buttons that truncated every name to its second word — Quote is an outline pill,
    names get two lines, and the meta line shortens the trade instead of stranding its "·". At 360px
    the row used to throw its CTA onto a full-width line, making each card ~215px tall on the phones
    with the least screen; rows hold together to 320px at ~117px.
  - **You could tap "Offer services" and never become one.** `POST /provider/profile`, `/provider/skills`
    and `/provider/service-areas` were all built and specced, and **nothing in the app called any of
    them** — so the only providers that could exist were seeded ones. New `/become-a-provider`:
    headline, trades from the real bilingual taxonomy, pricing model, and a service radius for on-site
    work only. Three calls because they are three server rules that fail separately (profile always
    succeeds per doc 10; a skill needs the profile first; the area needs location_tracking consent,
    asked for rather than assumed) — a refused area leaves the profile and skills standing and says so,
    rather than claiming a setup that would silently never receive on-site work.
  - **Three more places the app spoke about the user without knowing them** (same family as the
    fixture-stats fix below): `me` defaulted to "Jean Mballa · +237 6 99 88 77 66", which is what you
    saw whenever `/auth/me` failed — and an expired 15-minute access token is a 401; `initialsOf()`
    put a lone `+` or a 👤 emoji in the avatar of anyone known by phone; and the provider screens
    rendered a blank avatar over the tier badge, introducing a user with no profile to themselves as
    "Building". All three say nothing and show a person glyph now, and the provider screens fall back
    to the signed-in account's name — which is not inventing a provider, it is naming the human
    holding the phone.
  - **The app and the server disagreed about language.** The server ranks a user's stored locale above
    `Accept-Language`, and first launch only DETECTS a device language and tells nobody — so English
    chrome was being served French skill labels, and would have sent French follow-ups and push copy.
    `LocaleService::reconcile` settles it on whoever was explicit (a device choice is pushed; an
    unconfirmed default adopts the account's) and refetches the taxonomy when that changes.
  - **Account IA**: "Offer services" and "Log out" sat under a "Help & support" heading, which is
    neither. Offer services has its own heading and hint and routes to signup or the dashboard
    depending on whether a profile exists; log out is a plain action at the end.
  - **NOT a bug, recorded so it is not re-chased:** the provider profile looked clipped by the tab
    bar in the first screenshot. Scrolling each tabbed page to its end over CDP shows every one stops
    40–50px above the bar. That was the fold.

- **A fact cache that returned a broken object on every hit, and a suite that could not see it.**
  Found by driving the new signup against the real API: the first trade listed 201, the second 500 —
  `FactDeriver::derive(): Return value must be of type FactResult, __PHP_Incomplete_Class returned`.
  `cache.serializable_classes` defaults to **false** in this Laravel, so the cache unserializes with
  `allowed_classes: false` and **any object read back from a serializing store comes out incomplete**.
  `derive()` cached the `FactResult` object, so it was correct on a MISS and broken on every HIT: on
  Redis, the first capability check of a flow works and the next 500s until the 5-minute TTL expires.
  That is the whole P0-17 access model — the gate in front of accepting paid work, listing a skill,
  everything. **The suite runs on the `array` store, which returns objects by reference and never
  serializes, so five green tests on this exact class proved nothing about it.** The fix caches two
  scalars and rehydrates (allow-listing the class would work too, but the default exists to keep
  object graphs out of caches, and a fact is a bool and an int). The new test drives the `file` store
  with `serializable_classes` false so a real round trip happens — **verified failing on the old code
  with the same TypeError and passing on the new**.

- **The app was inventing facts about the person using it.** Reported symptom: provider home showed
  ★4.9 / 94% on-time for a provider Discover correctly called unrated. The cause was a pattern —
  fixtures were the initial value AND the fallback for anything that failed, including a 404 meaning
  "this person has no provider profile". So a first-time tap on "Offer services" showed, as your own:
  the name Atelier Nkeng, tier 2, 4.9 stars from 68 reviews, and 640 000 FCFA to withdraw. Worse,
  `provider(id)` returned `profiles.p1` for any unknown id, so a real provider's card could render
  another person's reputation and testimonials under their name. Identity, stats, wallet, leads and
  active work all start empty now; illustrative content is a demo aid, a claim about the user is not.

- **A public site that sells the product, and seed data that reaches every feature.** The website's
  whole design system had been ~55 lines of inline CSS; it is now a real landing page (hero, trust
  strip, how-it-works, trades grid, a band for professionals, safety, JS-free FAQ, footer) whose every
  claim is something the platform actually does, publishing **no provider counts** — "3 plumbers" is a
  liquidity disclosure. On the data side the gaps were not cosmetic: **no provider listed a single
  trade and none had a service area**, so every trade page read "no one offers this" and
  `ProviderSearch` would return nobody for any on-site job; **no engagement was ever completed**, so
  `completed_at` was never stamped and reviews/warranties/metrics could not be seeded at all; every
  engagement was on-site, hiding the entire remote path; and the directory printed display names
  because the seeder put the person's name in `headline`, inverting that page's privacy rule.

- **The admin sections that did not exist at all** — Parties, Payment intents, Payouts and Activity
  logs. All read-only on purpose: identity is established by OTP and an approved document (a form
  would hand someone a tier they never proved), and a payment's state belongs to the gateway. The
  audit table had read `actor.name`, but users have no `name` column — the display name is on the
  party — so **every human action rendered as "System"**, the one thing an audit trail must never say;
  the same mistake was on the dispute, reconciliation and safety-alert pages.

- **The admin panel, looked at with real data in a real browser for the first time since the rework
  — and it was not what this tracker claimed.** Founder feedback: "the create and edit views for most
  sections are not there" and the UIs "look horrible". Both were right.
  - **The dashboard had been rendering inside half a column since the rework.** `OverviewWidget`
    declared `$columnSpan = 'full'` and never received it: Filament applies its column-span classes
    from the `<x-filament-widgets::widget>` component, and the bespoke view's root was a plain
    `<div class="hm-dash">`, so the declaration was silently ignored and the whole dashboard laid out
    in one 516px column of a two-column grid. Every figure wrapped mid-number ("5 280 / 000 FCFA"),
    the needs-attention panel wrapped every two words, and a third of the page was empty gutter. The
    "pixel-for-pixel fidelity to the approved mockup" claim below was **never true in a wide window**;
    it was presumably only ever checked narrow. Wrapping the view fixes it (3 × 341px KPI columns).
    Money cells also got `white-space: nowrap` — the thousands separator is a space, so "800 000"
    broke into two lines that read as two numbers.
  - **Four queues could be looked at and not opened.** Disputes, Reports, Safety alerts and Referrals
    had a list page and no view page. Worst case: the dispute queue truncates the complaint to 60
    characters and offers an Adjudicate button, so the decision was being made without the complaint
    being readable. All four now have bespoke detail pages in the dashboard's visual language, and
    every row action is defined once and shared by the row and the page.
  - **Reports could never be closed.** `resolved` and `dismissed` were valid statuses that nothing in
    the codebase could set — every report rested at `open` forever. This is the same "declared but
    nothing invokes it" pattern as the dead `FollowUpKind` cases, and it survived the audit above
    because the audit checked hedged DONE markers, not enum values nothing writes. New `ReviewReport`
    domain action; the table has no reviewer column, so attribution goes to the append-only activity
    log (P6-02) rather than a migration, and a second decision on a closed report is refused rather
    than overwriting the first one's attribution. 4 tests.
  - **Reconciliation exceptions had no page at all** — the dashboard had counted them as "needs a
    human" with the link beside that count set to `href="#"`. New Money > Reconciliation Exceptions
    resource (oldest first: a settlement gap worsens with age), detail page, dashboard link wired.
    Resolving records who and why and posts **no** ledger entry: picking accounts for a balanced
    adjustment from a queue dropdown is how you get a transaction that balances and is still wrong,
    against an append-only ledger. A money-moving correction stays a deliberate separate step.
  - **The skills taxonomy is now editable** — the first genuine create/edit surface in the admin.
    It is reference data with no state machine and no money attached, so a form is the right tool;
    until now the taxonomy could only be changed by editing a seeder. Delete is refused (it would
    orphan `provider_skills` and jobs). The two fields with teeth are labelled as such in the form:
    `risk_tier` feeds the AcceptPaidJob gate, so raising it can stop currently-verified providers
    from accepting that work, and `maintenance_interval_days` is what makes a trade emit
    `maintenance_due` follow-ups.
  - **Record titles**: dispute/report/alert/referral pages were headed by a bare UUID, identical
    between two open cases. They are named by what the case is about now. The referral queue showed
    both parties as 8-character UUID fragments; it shows names.
  - **One claim in the founder report was wrong and is worth recording as a method note:** the admin
    sidebar looked unlabelled and half-height in the first screenshots. That was an artifact of
    full-page capture (`captureBeyondViewport`) against a `position: fixed` sidebar — at true
    viewport size it was always fine. **Judge layout from viewport-sized captures.**
  - Still open from that founder feedback: the **public website** (essentially undesigned — the whole
    "design system" is ~55 lines of inline CSS in `layouts/public.blade.php`) and the **Ionic app**
    (density, clipped rails, two "+" buttons on Jobs). Also spotted and not yet fixed: **provider
    home shows fixture stats** (★4.9 / 94% on-time) for a provider that Discover correctly reports as
    unrated — P6-12 requires on-time to be null below a 5-job floor — and `DemoSeeder` mixes faker
    junk ("Maiores recusandae" as a category) into otherwise real Cameroon data.

- **Audited every hedged DONE marker in this tracker. One was an unmet acceptance criterion, and
  chasing it found a real performance defect on the provider-search hot path.**
  - **P1-06 — "100k/<50ms benchmark left to a perf script".** The script never existed, so the
    criterion had never been checked. It exists now (`php artisan perf:geo-benchmark`) and seeds real
    Yaoundé/Douala bounding boxes rather than uniform noise — an index benchmarked against uniformly
    random points is not benchmarked. **Result: criterion MET** — 100k addresses, 1km radius, index
    served, **p95 10.0ms** against the 50ms budget. It does *not* hold at a 5km radius (p95 161ms),
    but that is a result-set-size effect, not an index one: at that radius ~5% of the table matches
    and a bitmap scan materialises every candidate before `LIMIT` can help. Worth knowing before
    anyone exposes a wide-radius search.
  - **A genuine defect, previously invisible: `ServiceArea::scopeCovering` was a SEQUENTIAL SCAN.**
    Its distance comes from a *column* (`radius_m`, each provider's own reach), and a GIST index
    cannot bound a search whose radius differs per row. It measured ~1ms in every test, because
    providers cluster in cities so the scan hits matches immediately and `LIMIT` exits early. **A
    point matching little or nothing has no early exit and scans the whole table: 131.8ms at 50k
    areas, growing linearly with every provider who signs up** — on the P2-04 provider-search and
    P8-03 dispatch path. Fixed by adding an index-served constant bound (`MAX_RADIUS_M`, a strict
    superset of the exact predicate, so the answer is unchanged) ahead of the per-row check:
    **index now used, worst case 131.8ms → 3.7ms.** The dense case costs ~8ms more (1.0 → 9.0ms);
    that trade buys a bounded worst case and removes the linear growth, which is the right trade for
    a hot path. 105 coverage/proximity tests still green.
  - **The other hedges are all external or hardware, and none hides unbuilt code**: CinetPay live
    sandbox, FCM delivery, WhatsApp template approval and SMS all sit behind credentials with their
    adapters written and config defaulting to `fake` (verified: all four default to `fake`); the iOS
    build needs macOS (verified: no `mobile/ios` directory, consistent with the claim). P4-05's
    "mic capture and playback decode unverified" stands — the multipart *transport* is now proven on
    device, capture itself needs a real microphone.

- **The follow-up catalogue is now mostly real, not just declared.** `FollowUpKind` listed sixteen
  kinds; nine were reachable and seven were dead enum cases. Doc 07 §"The catalogue" specifies all
  sixteen with exact triggers, delays and cancel conditions — so these were **specified and unbuilt**,
  the same pattern as the P7-08 pipeline. Six now fire (`maintenance_due` plus the five below), each
  off an event the domain already published:
  - `job_unquoted` (job.published +6h, cancelled by the first offer), `site_visit_reminder`
    (24h + 2h before, cancelled on completion), `job_starting_soon` (24h + 1h before a booked window,
    and only when the assignment carries one), `awaiting_approval` (deliverable.submitted +24h — a
    different message from the auto-approve *deadline* warning), and `payout_ready` (money released
    into `provider_payable`, immediate, **over a threshold**: nudging someone to withdraw a pittance
    costs us a message and costs them a transfer fee).
  - All got per-kind copy in both languages instead of the generic fallback.
  - **Three remain deliberately unwired, and none is a silent gap.** `payment_due` has no trigger in
    this domain at all — there are no invoices; money moves through escrow and milestones.
    `abandoned_draft` needs a `job.draft_created` event nothing publishes. `check_in_overdue` needs an
    en-route ETA the app never captures, and P6-06 already covers a no-show's *safety* side as a staff
    alert. Each needs a product decision or a new event, not just wiring.
  - **A latent bug fell out of giving kinds their own copy**: `FollowUpDelivery::copy()` resolved
    templates with **no replacements**, so any per-kind string naming a variable would have gone out
    with a raw `:service` in it — the one defect a recipient definitely notices. Variables are now
    resolved (the service in the target's own language, since the taxonomy is bilingual) and `copy()`
    refuses to send a string with an unresolved placeholder, falling back to the generic wording.

- **P7-08's pipeline — the quarter of a task that was carried as done and never built.** The build
  plan names four things (customer list, pipeline, manual follow-up, do-not-contact); three existed,
  "pipeline" appeared nowhere in the codebase, and this tracker had parked it as "a client/admin UI
  surface". Now real: `ProviderPipeline` + `GET /v1/provider/pipeline`, four stages read straight off
  rows that already exist, so a provider maintains nothing by hand.
  - **Two deliberate refusals.** It is **not a forecast** — nothing is weighted by a probability of
    closing, because the platform knows what was offered/quoted/won/finished and does not know what
    will close; the screen says so in as many words. And an **unpriced lead is counted but adds no
    money**: falling back to the job's budget where the customer named one is fair, inventing a
    figure where nobody has is overstating the funnel in exactly the direction a provider wants to
    believe. Both are tested.
  - The screen became the CRM surface the plan describes — a Pipeline / Clients segment, renamed
    **"My business"** since it no longer only lists clients. The empty-client-book state moved inside
    the Clients tab: as a top-level branch it hid the funnel from a new provider with live leads and
    no completed job, which is precisely the provider who needs one.
  - Bars are hand-drawn divs on a shared scale — four numbers do not justify a charting payload on a
    low-end Android. Verified in a browser against a seeded funnel (3/1/1/1 → bars 100/33/33/33),
    both tabs on real data, no sideways scroll at 360px, light and dark.

- **`NATIVE_API_ORIGIN` is now guarded instead of merely commented.** It is still the placeholder
  (no domain registered), and shipping it produces an app that installs, opens and renders while
  reaching nothing — quiet enough to survive a smoke test, the same shape as the native-transport
  bugs. `npm run check:native-origin` warns on an ordinary build and **fails hard when the store
  pipeline sets `HM_RELEASE=1`**; it also rejects a non-https origin. Wired into `verify:frontend`
  and into CI as a warning (a gate there would fail every run over something nobody can fix until a
  domain exists). All four paths tested: placeholder+dev → warn, placeholder+release → fail, real
  https+release → pass, http+release → fail.

- **On-device round three: secure storage finally PROVEN, and the packaged app was profoundly
  broken in two ways no browser can show.** Driven by attaching to the APK's WebView over the debug
  protocol (`adb forward` → CDP) rather than tapping coordinates — this is the technique that makes
  device work tractable, and it is how all of the below was found.
  - **The two bugs.** Capacitor routes `fetch` through the OS HTTP stack (enabled app-wide in round
    two so the WebView could reach the API at all). Its patched `fetch` is not a drop-in:
    1. **Every write arrived empty.** The patch reads the request BODY from its second argument
       only, and openapi-fetch calls `fetch(request)` — one `Request`, no init. Headers survive; the
       body does not. An OTP request reached the server with no phone number and came back 422 "the
       phone e164 field is required", which reads exactly like a client validation bug.
    2. **Every read came back as nothing.** The synthesized response reports `Content-Length: 0`
       whatever the body is (a 679-byte list claiming to be empty). openapi-fetch uses that header
       to decide whether to parse, so every read resolved to `data: undefined`; callers threw on
       `data.data`, the offline cache caught the throw as "could not refresh", and each screen fell
       back to its FIXTURE. **The app looked healthy while showing demo data on every surface** —
       server logs showed traffic, the UI showed no error.
    Fixed in `mobile/src/app/api/client.ts` (`sendRequest` + `normalizeNativeResponse`). Verified:
    a real OTP login completes on device, and discover renders the server's own answer (UUID ids,
    "No ratings yet" against a null rating, the real taxonomy) where it had shown "4.9 ★ · 2.1 km".
  - **Multipart was broken on device too, and the fix carries it.** Voice notes and report photos
    were the one path left unproven. Against the real API from the device, posting a valid WAV to
    `/jobs/{job}/voice-messages`: the OLD shape (`fetch(new Request(…))` with FormData) returns
    **422** — the audio field dropped exactly like every other body — while the unwrap the fix
    performs returns **201** with a real `voice` message row, and the stored media is **1644 bytes**,
    precisely the 44-byte header + 1600 samples that were sent. The bytes survive the native
    transport and the re-generated boundary. (This exercised `sendRequest`'s multipart branch by
    replicating it exactly against the live endpoint, not by driving the in-app recorder — mic
    capture on an emulator is a separate problem, and it is the transport that was in question.)
  - **Secure token storage — VERIFIED, the claim P5-01 could only assert.** After a real login on
    device: `shared_prefs/CapacitorStorage.xml` (the PLAIN preferences file) contains **only**
    `authed=1` — no tokens; `WSSecureStorageSharedPreferences.xml` holds
    `capacitor-storage_refresh_token` and `..._access_token` as **base64 ciphertext**, neither
    resembling the `NN|…` Sanctum token nor the 64-hex refresh token. A **cold restart** (force-stop
    → relaunch) lands straight on `/tabs/discover` rather than `/welcome`, and the server log shows
    the restored session calling the `auth:sanctum`-gated `/auth/me` — so the bytes on disk are
    encrypted **and** the app decrypts them. Note the plugin's raw bridge `get` returns null (it
    expects the JS wrapper's tagged format), which is why earlier attempts read nothing.
  - **Reproduce**: `emulator -avd handyman -no-window -gpu swiftshader_indirect`; `HM_NATIVE_DEV=1
    npx cap sync android`; `gradlew assembleDebug`; `adb install -r`; then
    `adb shell cat /proc/net/unix | grep webview_devtools` → `adb forward tcp:9222 localabstract:<socket>`
    and speak CDP to `http://127.0.0.1:9222/json/list`. **Note: `Network.*` CDP events show nothing**
    — native-transport requests never touch the WebView stack; watch the `php artisan serve` log
    instead. The dev build exposes `window.ng`, so `ng.getComponent(host)` reads live signal state,
    which is how "the data arrived but the component still holds fixtures" was pinned down.

- **The provider client book (P7-08) — the CRM's missing screen, plus two real bugs it surfaced.**
  P7-08's backend has existed since Phase 7 (customer list with history + LTV, manual re-engagement
  on the same budget/consent gates, absolute do-not-contact) with **nothing in the app in front of
  it**. `/clients` is that screen, reached from provider Home and Profile — deliberately not a sixth
  tab, since six labelled tab buttons do not fit a 360px phone without truncating.
  - The two writes pull in opposite directions on purpose: the nudge rides the same gates as an
    automated follow-up (a provider cannot spam through us), while do-not-contact is the customer's
    veto — so a blocked row offers **no nudge affordance at all** rather than a button the server
    would refuse. The nudge is deliberately **not** queued offline: it is a marketing message, and
    reporting success for something the platform is about to decline would be a lie. The list itself
    is read-through cached (P5-02) — who you have worked for is still true without a network.
  - **BUG: `last_engaged_at` was not ISO-8601.** The list is an aggregate over a raw query, so no
    cast reaches it and Postgres handed back its own `"2026-07-28 05:15:57+00"`. V8 parses that
    string; **iOS JSC and older Android WebViews return Invalid Date** — precisely the client this
    product ships to. Now `toIso8601String()`, matching the API convention and the `format: date-time`
    the OpenAPI spec already declared. Regression test asserts the shape.
  - **BUG (wider): an unauthenticated API request without an `Accept: application/json` header
    returned 500, not 401.** Laravel redirects failed-`auth` guests to `route('login')`, which this
    app has never defined (no web route is behind `auth`; Filament runs its own), so the RFC 7807
    renderer never saw the exception — a framework 500 for what is only an expired token. Clients
    that omit the header are ordinary (curl, a proxy that strips it, an older HTTP library). Guests
    are no longer redirected anywhere; `redirectGuestsTo(fn () => null)` in `bootstrap/app.php`.
  - Also fixed `tools/lint-no-bare-strings.mjs`, which could not parse Angular's two-word
    `@else if (…)` and read the condition itself as user-visible copy.
  - **Verified in a real browser against the live API**, signed in as a seeded provider: both real
    clients render with correct singular/plural and relative dates, search folds accents ("aicha"
    finds "Aïcha Bello"), the no-match message appears, and **all three writes round-trip** — nudge
    scheduled, do-not-contact set, and the row standing down to the blocked state in place. Light,
    dark, 360/768/1200.
  - **Gotcha worth remembering:** the row's action bar was first called `.acts`, which is a **global**
    primitive in `customer/ui.scss` — it stretches any `ion-button` inside it and laid the row out on
    the cross axis, so the two controls stacked and centred. No rule in the cascade declared
    `flex-direction: column`, which made it invisible by inspection; the fix was a component-local
    class name plus an explicit axis.

- **Discover's "See all" is no longer a dead link.** `.cats` is a horizontal scroller, so 10 of the
  14 categories sat behind a swipe. It now expands the same tiles into a grid (4 across at 360px via
  `auto-fill`) and the label becomes "Show less" — no request, works offline, since the taxonomy is
  already in memory.
  - **"Map" was dropped** rather than built, on the founder's call: a map of providers would have
    required publishing provider location, reversing P2-03's deliberate stance, and the link had
    been decorative since the mockup. Discover now has **zero `href="#"` links**.

- **On-device pass, round two: native networking fixed, and a compatibility bug that broke every
  write on older WebViews.** Both decisions from round one were taken: `mobile/android` and
  `mobile/ios` are **no longer gitignored** (the native projects hold app-specific config — manifest,
  icons, signing, plugin registrations — that has nowhere else to live; their own generated
  `.gitignore` already excludes the parts that really are build output), and **`CapacitorHttp` is
  enabled app-wide**.
  - **BUG 2 fixed.** With `fetch` routed through the native HTTP stack, the WebView reached the
    plain-HTTP dev API for the first time (`HTTP 200` where it had thrown "Failed to fetch"). Native
    transport also skips CORS, so the preflight before every request disappears. The trade recorded
    in `capacitor.config.ts`: multipart uploads (voice notes, report photos) and binary responses
    (the media route) now use a different implementation on device than in the browser, so those are
    the paths to re-check after any Capacitor upgrade.
  - **BUG 3 (the serious one): `crypto.randomUUID` does not exist before Chrome 92.** The app minted
    every Idempotency-Key with it — in `AuthService`, all 15 `ApiService` writes, the offline write
    queue, the quote composer. On the emulator's WebView (Chrome 91) **every unsafe write in the app
    threw**, and `AuthService` catches and walks on, so the app advanced to the code-entry screen
    having never requested a code. Android's System WebView is updatable but frequently isn't — a
    cheap handset that has never had a WebView update is precisely this product's target device.
    Replaced with `core/uuid.ts`, which prefers `randomUUID`, falls back to `getRandomValues` (these
    are idempotency keys: a repeat would make two distinct writes look like a replay of one), and
    only then to `Math.random`.
  - **Verified after the fix**: the app reached the API and rendered the server's own answer — a
    real OTP round trip, and a genuine "That code isn't right" rendered from a real rejection. Before
    the fix the same tap produced no request at all.
  - **Fixed (follow-up): a refused OTP request no longer sends the user to wait for nothing.**
    `requestOtp` discarded its result, so a rate limit or a rejected number still advanced to the
    code screen — the user sat there until the resend timer expired with no idea why. It now returns
    `sent` / `refused` / `unreachable`, keeping the three apart the same way the write queue
    separates an HTTP error from a transport failure: a REFUSAL is the server answering and is shown
    in place; only a thrown request takes the offline path. **Verified against a real rate limit**
    (P1-02's 3/hr per phone, driven for real rather than stubbed): the app stays on welcome and
    renders the server's own words — "Too many verification codes requested. Please wait and try
    again." — in the danger tone where the hint would be.
  - **STILL NOT verified**: secure token storage at rest. It needs a completed login, and the OTP
    limits (3/hr per phone, 10/hr per IP — P1-02, working as designed) were exhausted by the
    debugging above. Everything up to the final verify step is now proven on device.

- **First on-device pass (Android emulator) — the APK really runs, and two real networking bugs.**
  No AVD existed; created one (`android-31`, x86_64), booted it headless, installed the debug APK.
  - **Verified on the device**: the app installs and launches with no crash, renders correctly
    (icon, bilingual copy resolved, layout, light theme), the **SecureStorage plugin is registered**
    (`Capacitor: Registering plugin instance: SecureStorage`), and real touch input navigates
    welcome → OTP screen.
  - **BUG 1 (fixed): a packaged build could not reach a plain-HTTP dev API.** Android 9+ blocks
    cleartext, so `10.0.2.2:8100` was refused — *"Cleartext HTTP traffic to 10.0.2.2 not permitted"*.
    Worse, it failed **silently**: `AuthService` treats an unreachable backend as "offline" and walks
    on to the next screen, so the app looked like it worked while talking to nothing (the OTP screen
    appeared with no OTP ever requested). Fixed with an opt-in `HM_NATIVE_DEV=1` cleartext flag in
    `capacitor.config.ts` (never on for production, where the API is HTTPS) **plus a debug-only
    `AndroidManifest.xml` overlay** carrying `usesCleartextTraffic`. After it, a request from the app
    reached the API for the first time.
  - **BUG 2 (open): mixed content blocks the WebView's own `fetch` on native.** Capacitor serves the
    app from `https://localhost`, so *any* plain-HTTP API is mixed content and blocked in the WebView
    regardless of the cleartext fix — `CapacitorHttp` (native transport) got through, `fetch` did
    not. Two candidate fixes, both with consequences worth a decision: enabling `CapacitorHttp` app
    wide (routes all `fetch` natively — also removes CORS preflights, but changes how multipart
    uploads and blob responses behave, which is how voice notes and report photos work), or serving
    the app over `http://localhost` in dev via `server.androidScheme`.
  - **⚠ The manifest fix lives in `mobile/android/`, which is GITIGNORED** — so it is destroyed by the
    documented `npx cap add android` regeneration step. Committing the native projects is standard
    Capacitor practice precisely because they hold app-specific config (manifest, icons, signing);
    the current ignore makes any native customization unkeepable. **A decision, not a defect** — left
    for the founder rather than silently reversing repo policy.
  - **STILL NOT verified**: that a refresh token written by the app's own code path is encrypted at
    rest. The plugin's native `set` rejects a raw-bridge call (it expects the JS wrapper's tagged
    format), and driving the app's real `secureStore` needs a completed login, which BUG 2 blocks.
    The claim rests on the plugin's own guarantees until that is possible. Reproduce the setup with:
    `avdmanager create avd -n handyman -k "system-images;android-31;default;x86_64" -d pixel_5`,
    `emulator -avd handyman -no-window -gpu swiftshader_indirect`, then
    `HM_NATIVE_DEV=1 npx cap sync android && ./gradlew assembleDebug && adb install -r …`.

- **The discover search box works.** It searches **trades, not providers** — what a customer types is
  the thing they need done ("fuite", "AC repair"), and the taxonomy is bilingual and indexed for
  exactly that (P1-07b); matching provider headlines would return business names, which is not the
  question being asked. Picking a result filters the rail and collapses the search.
  - Debounced at 250ms (one request per burst of typing, on networks where a round trip is
    expensive), and a result set for a query the user has already typed past is discarded.
  - **`/skills/search` covers LEAF trades only** — right for the index, but it meant typing a
    perfectly natural word that happens to be one of our own CATEGORIES ("plumbing", "plomberie")
    was answered with "no trade matches", while the app was holding the category list in memory.
    Categories are now matched **locally**: 13 bilingual labels, no request, works offline, and
    accent-folded so "electricite" finds "Électricité" — a customer on a phone keyboard will not
    reach for the accent. Categories are listed first, being the safer pick when someone is unsure
    which specific trade their problem falls under.
  - **Verified** in a real browser: "plumb" → the Plumbing category (server FTS alone returns nothing
    for it), "electric" → the category plus both electrical leaves, "zzzzqq" → the no-match message,
    and picking a result narrows 8 providers to 1, clears the box and hides the list. One request per
    query, three for three queries.
  - **Harness note**: an early run disagreed with itself because it raced a cold API — the giveaway
    was the rail showing 5 cards (the fixture count) instead of 8. Re-run warm, it is consistent.
    The local category match legitimately can't work before the category list has loaded.

- **Discover categories actually filter.** The category rail was decorative — twelve real trades that
  did nothing when tapped. Now a tap narrows the provider list to that trade **server-side**
  (`?skill=`), a second tap clears it, the tile fills to show it is selected (a fill, not colour
  alone, so the state survives a colour-blind reading or a dimmed screen in sunlight), and the
  section heading changes from "Top providers near you" to "Providers in this category" — leaving the
  old heading over a filtered list would quietly misdescribe it. A stale response for a
  since-changed filter is discarded rather than overwriting what the user is looking at.
  - **Verified** in a real browser: 8 providers → 1 for `skill=hvac-and-refrigeration`, tile marked,
    heading changed, and back to 8 on clear. (The doubled requests in the network log are CORS
    preflights from the cross-origin dev setup — one GET per load, and none in production, which is
    same-origin.)
  - Still decorative on this screen: the search box, "See all" and "Map".

- **The provider profile no longer needs a job (`GET /providers/{party}`).** The profile screen could
  only get a headline by re-reading a job's match list, so it **bailed out entirely without a `?job=`
  in the URL** and showed demo data — which is exactly the path a customer takes when they tap a card
  on the discover rail. A public profile should not depend on the route the viewer arrived by.
  - Same minimisation as the browse list (it is the same resource). Returns **404, never an empty
    profile**, when the provider is suspended or blocked in either direction: a party id is guessable
    from any page that shows one, so a still-loadable profile would make a block cosmetic — hiding
    them from the list is only half the job.
  - The screen keeps its `?job=` for the *offer* flow, which genuinely needs a job. Only the read
    stopped depending on it.
  - **A display bug this exposed**: with no listed trade, the sub-headline falls back to the headline
    — which is already the name directly above it, so the profile printed the same string twice. It
    now renders only when it differs.
  - **Verified**: 4 more backend tests (suite 437 passed) and a real browser tapping through discover
    → profile with **no `?job=` in the URL**, showing that provider's real bio, "No ratings yet" and
    the below-floor on-time treatment.

- **The discover rail is real (new public `GET /providers`).** The app's home screen browsed five
  hard-coded providers. `ProviderSearch` couldn't back it: it answers "who can do THIS job", needing
  a job for the skill, mode and address, and it is owner-gated — the wrong shape for a customer who
  is still deciding what to ask for.
  - `PublicProviderDirectory` is the same act one step earlier: browse a trade, see who does it. Its
    listing rules are deliberately **identical to the crawlable `/services` directory**, because a
    stranger looking at who offers a trade should not get two different answers depending on which
    door they came through — suspended excluded, `accepts_direct` only, ranked by verification tier
    then shrunk rating. A **category** slug matches every trade beneath it, so a customer browsing
    "Plumbing" needn't know which leaf their problem falls under. An unknown slug returns nobody
    rather than everybody.
  - **Unauthenticated, with an optional Bearer.** The guard is asked directly (`user('sanctum')`)
    rather than via middleware, so an anonymous visitor gets an answer and a signed-in one gets
    their **blocks honoured** (P6-07) — a blocked party must not reappear just because the customer
    took a different route to the same list.
  - **A separate `PublicProviderResource`, not a reuse.** `ProviderProfileResource` exposes
    `display_name` conditionally on whether the `party` relation happens to be loaded — safe today,
    but one stray `->with('party')` would silently start leaking real names onto a public endpoint.
    Here the name has no code path at all. A test asserts the name appears **nowhere in the
    payload**, not merely that the field is absent.
  - **On-site vs remote without leaking location**: filtered on whether a provider has declared any
    service area, and surfaced as a single `serves_onsite` boolean. The areas themselves stay
    private. The directory takes a `travels` **bool**, not a mode — the P2-02 scan test caught an
    inline `=== 'onsite'` and was right to: modes belong to jobs, so the controller translates once
    through `EngagementModePolicy::supportsDispatch()`, which is exactly the property that requires
    a service area.
  - **Two UI bugs the fixtures had been hiding**, both visible the moment real data arrived: a
    "null km away" chip (the public resource carries no location, by design) and a bare **"★ 0"** for
    an unrated provider — which reads as a terrible score rather than no score, the opposite of the
    truth for someone new, and contrary to P6-09/P6-12. Now "No ratings yet", in muted grey rather
    than the rating's amber.
  - **Verified**: 10 backend tests (suite 433 passed), and a real browser against the live API —
    anonymous `GET /providers` returns 200 with no `display_name` anywhere, and the signed-in rail
    shows 1 on-site vs 7 remote providers from the database, the segment changing the pool
    server-side. **Note**: the discover TAB itself sits behind `auth.guard`, so signed-out users are
    still sent to `/welcome`; the public surface for the pre-signup case remains the Blade
    `/services` directory. The endpoint being public costs nothing and is what an unauthenticated app
    shell would need.

- **The messages tab is real (new `GET /conversations`).** The chats screen was three hard-coded
  French placeholders — one of the last surfaces still showing fixtures to a signed-in user. There
  was no conversations-index endpoint at all: the thread has always been reachable only via
  `/jobs/{job}/messages`, so a client could open a conversation but never *find* one.
  - `UserConversations` builds the list from `conversation_participants` — **membership is the only
    thing that decides what appears**, the same gate the thread read/post endpoints use, so nothing
    can be listed that the user could not then open. One query for the last message of every
    conversation rather than one per row: the list grows without bound for a busy provider, and N+1
    would be felt on exactly the connection this product can't rely on.
  - **`counterpart_name` is per-viewer** — the provider for a customer, the customer for a provider —
    which is why it is computed in a read model rather than a resource. Before an engagement exists
    there is no provider to name, and it falls back to the job title rather than a placeholder.
  - **The preview sends the KIND, never a rendered sentence.** A server-narrated message
    ("quote accepted") must appear in the reader's language, and only the client knows which that is;
    prose here would hard-code one locale into the API. `preview` is populated only for free-form
    text, and the client renders everything else from the same `workspace.*` copy the thread uses.
  - **`POST /conversations/{id}/read`** ships in the same slice, because without it `unread_count`
    could only ever grow. It is forward-only: a second device reporting a stale read must not
    resurrect messages already seen.
  - Client: the tab loads on every entry (an unread badge that is stale is worse than one that takes
    a moment), clears the badge optimistically on tap, and the workspace marks the thread read on
    open so the two screens agree. The row list is cached by P5-02, so it opens populated offline.
  - **Verified**: 8 backend tests (membership gating both ways, per-viewer counterpart, unread rising
    and clearing, own messages never unread, narrated-kind preview, forward-only marker), plus a real
    browser against the live API — the tab shows the **actual** conversation and not the fixtures,
    with a 4-message badge that clears to 0 after opening the thread and **stays cleared on reload**.

- **PWA, secure token storage, and a real Android build (P5-01).** The offline layer from P5-02 is
  unreachable without this: you cannot queue a message in an app that won't open.
  - **Service worker.** The stock `ng add @angular/pwa` config precaches CSS/JS but **not
    `/assets/i18n/*.json`** — offline that leaves every label as a raw dotted key, the exact failure
    the UI bar warns about. They are now a prefetch group. Registration is skipped on native, where a
    second cache in front of already-local files buys nothing and fights the store's update cycle.
  - **Manifest from tokens.** `tokens/build.mjs` now emits `manifest.webmanifest`, so the install
    splash can't drift from the brand. `index.html`'s two `theme-color` metas are the one place a
    colour genuinely must be a literal (a meta tag is read before any stylesheet) and the
    no-literal-colour lint doesn't reach `src/index.html` — so the token build **asserts** them
    instead of trusting a comment.
  - **Secure token storage.** A refresh token is a 30-day credential that mints access tokens on
    demand; it now lives in Android's EncryptedSharedPreferences / the iOS Keychain rather than the
    plain preferences file next to the theme setting. On web there is no OS keystore to reach and it
    falls back to Preferences — a real limitation, stated rather than papered over. An install from
    before this rewrites its plaintext token into the secure store on first launch.
  - **Native URLs — a bug the APK would have shipped with.** The prod environment uses a relative
    `/api/v1` and `window.location.hostname`, which are right for a PWA and meaningless in a packaged
    app whose origin is the device: every request would have resolved to `capacitor://localhost`.
    Both are now chosen at runtime from `Capacitor.isNativePlatform()`.
  - **Verified**: the app **opens and renders fully offline with translations resolved** (real
    browser, service worker controlling, network cut at the CDP level), and `gradlew assembleDebug`
    produced a **5.3 MB debug APK**. Building it needed SDK platform 36 + build-tools 35 installed
    into the machine's existing Android SDK (it topped out at 33/32).
  - **NOT done**: iOS is not built — it needs macOS/Xcode. Nothing in the codebase is
    Android-specific; `npx cap add ios` is the step. `mobile/android` and `mobile/ios` stay
    gitignored (pre-existing decision), so the native project is regenerated with
    `npm run build && npx cap add android && npx cap sync android`.
  - **NOT verified**: anything that needs a device or emulator — the secure store really using the
    Keystore, the app running from the APK, push registration. Only the build is proven here.

- **Offline-first: a read cache and a durable write queue (P5-02).** On the networks this product is
  for, "you're offline, try again" is not an instruction a user can follow. Two halves, in
  `mobile/src/app/core/offline/`:
  - **Reads.** `OfflineCache.through(key, loader)` caches what the API last answered and serves it
    when the loader fails. This replaced a genuinely bad behaviour: every screen's `catch` fell back
    to the DEMO FIXTURES, so a customer with no signal saw a stranger's fictional plumbing job
    presented as their own. Now they see their own jobs, slightly old. Cached: jobs list, job
    detail, thread, the bilingual skills taxonomy (per locale — it changes quarterly and lets
    someone browse every trade offline) and the provider work detail. Cleared on logout; pruned at 7
    days.
  - **Writes.** `WriteQueue.submit()` persists a mutation to IndexedDB **before** attempting it,
    then replays FIFO when the API is reachable. The single load-bearing field is
    `idempotencyKey`, **minted once at enqueue and reused on every attempt** — that is what makes a
    write the server already accepted (but whose response was lost) replay into the stored response
    from P0-06 rather than a second message, second work session, second escrow release.
  - **Deliberate choices.** IndexedDB, not a SQLite plugin: it is the one engine that behaves
    identically in the browser, Android WebView and WKWebView with nothing to build, and it doesn't
    block the UI thread the way `localStorage` (what Capacitor Preferences uses on web) would on a
    low-end phone. Strict FIFO with no skipping past a backing-off entry, because a thread that
    reorders itself is worse than one that pauses. A network failure never exhausts the retry
    budget — being offline for a day is not an error — but a server that keeps refusing gives up
    after 8 tries and says so, since an invisible forever-retry is worse than an honest failure. The
    outbox is **dropped on logout**: those writes would otherwise replay under the next session's
    Bearer and attribute one person's actions to another.
  - **What is queued vs not.** Chat messages, check-in/check-out (with the GPS fix taken at the
    moment of the action, not of the replay), provider status signals, milestone approval. Creating
    a job is NOT queued — a queue can promise eventual delivery, never an id the next screen needs.
    Milestone approval is queued but never shown as released until the server says so: escrow is the
    customer's money and an optimistic "released" that later fails is not a cosmetic error.
  - **UI.** A token-driven connectivity strip inside the page header (in flow, so it can never cover
    a title, tab bar or composer), on the workspace, jobs, job-detail and provider work screens. It
    says three different things — offline / catching up / refused — and renders nothing when there is
    nothing true to say. Messages composed offline appear immediately, dimmed, with a hollow ring;
    the server's copies replace them when the queue drains.
  - **Four real bugs, all found by running it rather than building it:**
    1. **The app froze on boot.** The effect that flushes on reconnect called `flushNow()`
       *synchronously inside its own tracking context* — which reads `pending` and then writes it,
       and a fresh array is never `Object.is`-equal to the last, so it re-triggered itself forever.
       The main thread was unresponsive within one second. `untracked()` fixes it.
    2. **A reconnect mid-flush replayed the first message twice.** `flushNow` read the queue, awaited
       its disk writes, then wrote the whole list back — resurrecting an entry the in-flight flush
       had sent and removed across that gap. It now patches by id instead of replacing.
    3. **A slow outbox read could resurrect a sent write** the same way; flushes now wait on it.
    4. **`ion-icon` / `ion-spinner` fetch their assets in a lazy chunk** — so the offline banner's
       icon was the one thing that couldn't load offline (a real ChunkLoadError the instant the
       connection dropped). Every mark in the offline UI is now drawn in CSS.
    Also fixed: the strip was transparent over a translucent header, so scrolled message bubbles
    showed through the warning; and `client.ts` now resolves `fetch` per request instead of letting
    openapi-fetch capture it at module load — without that, a test that swaps the transport was
    silently talking to the real API.
  - **Verified**, since a green build proves nothing here: 5 Karma specs in real Chrome against real
    IndexedDB (stable over 3 consecutive runs), **plus a real browser driven through an actual
    airplane-mode episode** — load the workspace, cut the network at the CDP level, type two
    messages, and both appear dimmed with 2 durable outbox rows carrying 2 distinct keys; restore the
    network and the strip clears, the marks clear, the outbox empties — and the **database holds
    exactly one row per queued message**. A separate probe replayed one Idempotency-Key against the
    live API and got a byte-identical response with the message count up by exactly 1.
  - Prod build green, i18n parity 408 × 2, colour + bare-string linters clean. No backend change.
  - **NOT verified**: the failed-state strip (retry/discard) has no live reproduction — it needs a
    server that refuses a queued write; only its unit test covers it.

- **Voice notes (P4-05) + the media access rail.** Speaking a problem is far easier than typing it in
  a second language, so a voice note is a **first-class thread entry** (kind `voice`) with its audio
  attached as media — riding push, live broadcast and the REST thread with no special cases.
  - **Media was previously unreachable.** `storage_path` was returned and nothing could fetch the
    bytes, so job-report photos had no way to be shown either. New `GET /api/v1/media/{media}`
    streams a file, authorized by **what it hangs off** (`MediaAccess`: conversation participants for
    a message; the worker or customer for a report) — never by holding the id, so a stranger with a
    valid id gets 403. Verification documents are deliberately excluded: they keep their signed,
    audited route (P6-01/02), and routing them here would bypass the view log.
  - `POST /jobs/{job}/voice-messages` (multipart) writes message + media in ONE transaction — a row
    pointing at a missing file is worse than a failed send. The measured duration rides the payload
    so a bubble can size itself without downloading the audio.
  - **Client**: a `VoiceRecorderService` around MediaRecorder that picks the first container the
    browser supports (webm/opus on Chrome, mp4 on Safari) and **always releases the microphone** —
    leaving the recording indicator lit reads as being spied on. Playback fetches the bytes **with
    the Bearer** and plays a blob URL, because an `<audio src>` cannot carry a token; object URLs are
    revoked on destroy. The mic affordance is hidden where the browser can't record.
  - **Two real bugs found by uploading actual files rather than trusting fixtures:**
    1. **The mime allow-list used spec names, not sniffed names.** A genuine WAV detects as
       `audio/x-wav`, an m4a as `audio/x-m4a` — so real recordings were rejected with a 422. Widened
       to the sniffed variants, with a regression test.
    2. **An empty recording 500'd** on the `media_bytes_check` constraint. `StoreMedia` now throws
       `EmptyUpload` → a clean 422, which guards **every** media path, not just voice.
  - **Verified**: 7 backend tests, plus a live round trip through the running API — upload returned
    201 with `kind: voice`, the duration payload and a media URL; the authorized fetch returned 200
    with the right content type and **byte-exact** contents; the thread read lists both notes with
    their URLs.
  - **NOT verified**: microphone capture (needs a real mic and permission) and in-app playback of a
    real note — the browser renderer became unresponsive, and a synthetic WAV wouldn't decode. The
    transport either side of playback is proven; the `<audio>` decode step is not.

- **Presence: "Online" in the workspace header (P4-04 complete).** The other party's live presence,
  on a **presence channel** alongside the private one that carries messages.
  - **One channel registration serves both.** Laravel strips the `private-`/`presence-` prefix before
    matching, and an array return is both a truthy authorization for the private channel and the
    member payload for the presence one — so `engagement.{id}` now returns `['id' => …]` or `false`.
    The same participant rule guards both; a stranger is refused either way (tested).
  - **The member payload is the user id and nothing else.** Presence data is visible to every other
    member, so it carries the minimum that answers "is the other party here". A name would be
    redundant — participants already know each other post-engagement — and isn't worth putting on the
    wire. A test pins `user_info` to exactly `['id' => …]`.
  - **Two subscriptions, deliberately.** Presence is a separate channel from messages so it can never
    disturb the path that must not break. That made teardown a real hazard: Echo's `leave(name)` drops
    the public, private AND presence variants, so leaving presence would have killed live messages.
    Both teardowns use **`leaveChannel`** with the explicit prefix instead.
  - Presence is **live-only**: when the socket is down we stop claiming anyone is there rather than
    asserting they left, and nothing is persisted.
  - **Verified with a second authorized connection**: both channels subscribe, "Online" is correctly
    absent while alone (self is filtered out of the member list), appears when the peer joins, clears
    when the peer disconnects — and a live message still arrives exactly once afterwards, confirming
    the split teardown didn't regress the message path.
  - Also fixed: two Blade-style `{{-- --}}` comments had crept into the Angular workspace template.
    One was a compile error; the other slipped past the build and would have rendered as a broken
    interpolation.

- **Typing indicators (P4-04 remainder) — landed on the second attempt, verified both directions.**
  A typing hint is a **client event** (whisper): participant-to-participant through Reverb, never
  touching the API or the database. Nothing is persisted, so a missed whisper costs nothing.
  - **Why the first attempt failed, and what fixed it**: Echo's `whisper` / `listenForWhisper`
    wrappers silently did nothing — the frame reached the socket (proven with a raw bind) but never
    reached the handler. The rewrite used the raw pusher-js channel, but looked it up lazily and the
    lookup guard returned early (`Pusher.prototype.channel` was never called — proven by patching the
    prototype). The fix is to **capture the raw channel at subscribe time**, inside
    `onEngagementMessage` where it is known to exist, and keep it in a map keyed by engagement. No
    lookup, no guard, no silent path.
  - **Behaviour**: throttled to one whisper per 2s however fast you type (Reverb rate-limits client
    events); the indicator lingers 4s after the last whisper and clears itself — there is deliberately
    **no "stopped typing" event**, because a sender who closes the app mid-word would strand the
    indicator forever. A whisper carrying our own id is ignored.
  - The label is **nameless** ("typing…"): the thread has exactly two sides so "who" adds nothing, and
    naming them would inherit the header's customer-shaped fallback (the known wart below).
  - **Verified end-to-end** with a second Pusher client authorized as the other party: the binding
    attaches (`typingBound: 1`), a peer whisper shows the indicator, it clears after the linger, our
    own id is ignored, and typing in the composer emits a whisper the peer receives. Throttling
    demonstrably collapses a keystroke burst rather than sending one per key.
  - Mobile build green, i18n parity 395 × 2, colour + bare-string linters clean. No backend change —
    client events never reach the server.

- **Public/SEO surface: a crawlable services directory (doc 08).** The public site was a stub — a hero
  and two empty cards. The bilingual taxonomy (P1-07) is the natural SEO asset, so it now backs real
  pages: 13 categories and 41 leaves, each a genuine search term, in both languages.
  - `GET /services` lists every category with its leaves; `GET /services/{slug}` is one trade — a
    category page lists its leaves, a leaf page lists **who offers it**. An unknown slug 404s rather
    than rendering an empty page.
  - **PII holds the same line as the API**: a visitor here is anonymous and pre-engagement, so a
    provider shows their public **headline**, verification badge and shrunk rating — never the
    display name, never a service area or coordinate. Suspended providers are excluded exactly as
    `ProviderSearch` excludes them. A test asserts the personal name is absent from the HTML.
  - **SEO head**: per-page `<title>`/description, canonical **without** `?lang=` (a translation is not
    a separate page), reciprocal `hreflang` for fr/en plus `x-default`, Open Graph, and JSON-LD
    (`ItemList` for the directory, `Service` for a trade) built in the controller — Blade's `@json`
    can't parse a multi-line array literal.
  - `sitemap.xml` (114 URLs — every page × both locales, with `xhtml:link` alternates) and
    `robots.txt`, which **disallows the grant URLs**: signed verification-document links and share
    tokens are grants, not content, and must never be indexed.
  - **Two real bugs found by looking at the rendered page rather than trusting green tests:**
    1. `public/robots.txt` existed as a STATIC file and shadowed the route — the web server answers
       it before Laravel ever routes, so the richer robots.txt was dead in production while the test
       passed (tests bypass static files). Removed the static file.
    2. **Parameterised translations printed raw on every Blade page.** The shared i18n source uses
       ngx-translate's `{{name}}`; Laravel substitutes `:name`. The page literally read "Find trusted
       {{service}} professionals". `i18n/build.mjs` now rewrites placeholders for the **Laravel output
       only** — the Angular file keeps the form it expects. This affected any parameterised string
       rendered from Blade, not just this page.
  - 407 backend tests green (13 in the web suite), PHPStan L6 + Pint clean, i18n parity 394 × 2,
    colour + bare-string linters clean. Verified in a browser in both languages.

- **Live workspace messages over Reverb (P4-04) — and a channel-auth bug that had never worked.**
  Realtime was scaffolded but inert: the channels were authorized and unit-tested (P4-03), yet
  **nothing in `app/` implemented `ShouldBroadcast`**, so no event was ever emitted.
  - **Broadcast rides the outbox**, which the Narrator's own contract already demanded ("fan-out is
    driven off the outbox — never inline"). `BroadcastOnOutboxMessage` turns a relayed
    `message.created` into `MessagePosted` on `private-engagement.{id}`, the sibling of the existing
    push listener. That ordering is the whole point: the Narrator writes INSIDE the transition's
    transaction (rule #11), so an inline broadcast would announce messages a rollback then erased —
    there is a test for exactly that. `ShouldBroadcastNow` because the relay is already a worker.
  - **Bug: channel authorization had never worked over HTTP.** Channels were registered with array
    callables `[ChannelAccess::class, 'method']`; Laravel reflects the callback to extract route
    parameters and reflection rejects arrays (`ReflectionFunction must be of type Closure|string`),
    so the rule never ran and every subscription was refused. The existing tests missed it because
    they called `ChannelAccess` directly, and the one HTTP test asserted only the unauthenticated
    403 — which passes for the wrong reason. Now closures (logic still in the unit-tested
    `ChannelAccess`), plus an HTTP test asserting a participant gets 200 and a stranger 403.
  - **Bug: a Bearer client could not authorize at all** — Laravel's `/broadcasting/auth` sits on the
    web (session) group. Added `POST /api/v1/broadcasting/auth` behind `auth:sanctum`
    (idempotency-exempt: a handshake, not a mutation) and named both guards on the channels.
  - **Client**: `laravel-echo` + `pusher-js`; a `RealtimeService` that degrades to silence (an
    unreachable Reverb leaves the app fully usable on REST — `connect()` never throws). The workspace
    **fetches first, then subscribes**, so a message landing mid-load is in the fetched thread rather
    than lost between the two, and **dedupes on message id** because the sender's own refetch and the
    broadcast both deliver it. Logout disconnects the socket, which was authorized with the dropped
    token. The thread read now returns `meta.engagement_id` (thread keyed by job, channel by
    engagement).
  - **Verified live** with Reverb + the relay running: the provider's open workspace received a
    message posted by the customer over the API **without a reload** (channel auth POST 200, text
    appeared in the DOM), and sending from the UI produced exactly **one** copy — the dedupe holding.
  - A typing footnote worth keeping: `npm run build` uses the PROD environment, so it could not catch
    `scheme: 'http' as const` making the TLS comparison a type error in the DEV config. The dev server
    caught it. A green prod build is not proof the dev configuration compiles.
  - 399 backend tests green (5 new), PHPStan L6 + Pint clean, mobile build green, client drift clean,
    i18n parity 386 × 2, colour + bare-string linters clean.
  - **Reconnect reconciliation (P4-07) — VERIFIED.** The workspace refetches the thread when the
    channel re-subscribes and when the tab/app returns to the foreground: anything sent while
    disconnected was never delivered, and REST is the authoritative record. Getting here took three
    attempts and each failure taught something worth keeping:
    1. Binding via `echo.connector.pusher` silently resolved to nothing — Echo's internals are
       private and version-dependent. Fixed by constructing the Pusher client ourselves and handing
       it to Echo via `client`, so connection state hangs off a reference we own.
    2. Raw connection state was still the wrong signal. A server that dies and returns drives
       `connected → connecting → unavailable → connected`, and that path did not deliver a usable
       edge. The signal that actually matters is **channel re-subscription** — "am I live again" —
       so reconciliation now hangs off `subscribed()`, skipping the first fire (the caller has just
       fetched over REST).
    3. **A restart leaves the channel dead even though the socket reconnects.** Reverb loses every
       subscription; pusher-js's automatic re-subscribe failed and never retried, leaving a
       `connected` socket whose channel was silently `subscribed: false` — reconciliation masked it
       for the missed message while no *further* live message would ever arrive. Reconcile therefore
       **always tears down and rejoins the channel**, which is what revives it. (An earlier
       "avoid pointless churn" guard was exactly wrong and was removed.)
    Verified end-to-end on the real failing cycle: load a thread → kill Reverb → post over the API →
    restart Reverb → the missed message appears **without a reload**, the channel reports
    `subscribed: true` again, and a message posted *after* the reconnect arrives live. No duplicates.
  - **Typing indicators — DONE** (second attempt; the first was reverted). See the entry above.
  - **Presence — DONE.** See the entry above. **Still open on realtime**: voice notes (P4-05).

- **Verified the customer discovery loop end-to-end, and fixed the provider's broken "Open chat".**
  - **The party-id fix is confirmed working in the app**: an open job → "Find providers" → the match
    card → the profile (URL now carries the **party** id) → "Send an offer" landed a real `job_offers`
    row with the correct `provider_party_id`. Before that fix this flow sent a *profile* id and could
    not have worked. Also re-checked the PII rule after changing the eager-load: the match resource
    returns `headline` and `party_id` but **no `display_name`**, and `id` ≠ `party_id` is visible in
    the payload. An empty shortlist for a skill no provider lists is the correct empty state, not a bug.
  - **Bug found and fixed — the provider's "Open chat" opened the wrong thread.** The workspace is
    keyed by the **job** (`GET /jobs/{job}/messages`) but the work detail navigated with the
    **engagement** id. The read 404s, the screen silently falls back to its demo fixture, and the
    provider sees a plausible-looking conversation that is not their job's. `WorkDetail` now carries
    `jobId` (the read already returned `job_id`), `openChat()` navigates with it, and the button is
    disabled until it is known — so it can never again open the wrong thread. Verified: the URL is now
    the job id and the provider sees the real thread, including the `Provider arrived` / `Work started`
    chips narrated by the earlier check-in and status actions.
  - **Realtime is genuinely not started**: `routes/channels.php` authorizes `engagement.{id}` and
    `user.{id}` (P4-03, tested), but **nothing in `app/` implements `ShouldBroadcast`** — no event is
    ever emitted, so there is nothing for a client to subscribe to. Making the thread live needs a
    broadcast event (`ShouldBroadcastAfterCommit`, to respect rule #11) plus Echo wiring in the app.
  - **Known cosmetic wart** (not fixed): the workspace header is customer-shaped, so a provider viewing
    it sees initials derived from the job title rather than the customer's name. The screen needs to
    be viewer-aware.

- **Provider quote composer on real endpoints (P2.5-01) — the provider section is now fully wired.**
  The lead screen's fixture "price + deposit + message" stub is replaced by a real **itemised**
  composer, because itemised is what the server actually stores and freezes.
  - **A direct offer now has two honest answers**: **Accept** it as it stands (P2-06, engagement at
    the offered price) or **Send a quote** (P2.5-01) when the work needs costing. The controller
    already accepted a quotation on an `offered` job, so this needed no new endpoint — the offer's
    embedded job id (added to the `Lead` model as `jobId`) is what `POST /jobs/{job}/quotations` takes.
  - **The total is a PREVIEW, never an input.** The server computes the subtotal from the lines and
    does not trust a client total (P2.5-01), so the sheet shows the same arithmetic — per-line totals
    and a running quote total — rather than a field. Verified live: the sheet read 122 500 and the
    stored `subtotal_minor` came back **122 500** from the server's own sum.
  - Composer fields: repeatable lines (kind — labour/material/travel/other — description, quantity,
    unit price), a deposit, a validity date (the API requires `valid_until` and rejects anything not
    in the future; defaults to +30 days), and notes. The **deposit is guarded against exceeding the
    quote** — it is what gets captured into escrow on acceptance (P3-13), so a deposit larger than the
    job is nonsense; the guard fires client-side and nothing is sent.
  - Verified end-to-end in the browser against the live API: two lines (1 × 85 000 labour, 3 × 12 500
    materials) submitted as a `submitted` v1 quotation with both lines, kinds, quantities, deposit
    40 000, validity and notes intact; the job stayed `offered` (it only engages on acceptance); and
    relaying the outbox showed the P2.5-06 orchestrator had scheduled **`quote_pending_customer`** and
    **`quote_expiring`** off the `quote.submitted` event.
  - Backend and OpenAPI untouched — the endpoint and contract already existed. 394 backend tests still
    green, mobile build green with no budget warning, API-client drift clean, i18n parity 386 × 2,
    colour + bare-string linters clean.

- **Provider Home dashboard + Profile on real data — and three bugs the wiring exposed.** Both screens
  now read the API.
  - **Home is COMPOSED, not a new endpoint**: wallet from `GET /provider/earnings`, in-flight count
    from `GET /provider/work`, reputation from the provider's own public `GET /providers/{party}/metrics`
    (P6-12). Each panel loads independently and keeps its fixture on failure, so one unavailable read
    never blanks the screen. Rating and on-time pass the API's **nulls straight through** — they render
    as "—" / "Building", never a flattering small-sample number. Verified live: 170 000 FCFA payable,
    2 active, rating "—" and on-time "Building" all matched the API exactly. "Withdraw" now routes to
    the Earnings screen instead of firing a fake toast (a payout is real money movement, P3-08).
  - **Profile is real**: display name, verification tier, listed skills as chips, service area. The
    service area shows a **radius**, not an invented city — the row stores a centre point + radius and
    nothing else. Empty states for "no skills" / "no service area" were exercised on the seeded
    provider before skills were added.
  - **Bug 1 — the client was sending a PROFILE id where a PARTY id was required.** `ProviderProfileResource`
    exposes `id` (the `provider_profiles` row) and the customer discovery loop used it for
    `POST /jobs/{job}/offers`, `GET /providers/{party}/metrics` and `/reviews` — all of which take the
    **party** id. They are different UUIDs, so "send an offer" and the profile screen's metrics/reviews
    could not have worked. The resource now carries `party_id` explicitly (documented in the spec as
    "NOT the handle other endpoints take"), and the customer service uses it. A test pins that the two
    ids differ.
  - **Bug 2 — `showProfile` never eager-loaded `party`**, so the `whenLoaded` `display_name` silently
    vanished from every response. Now loaded, along with `skills.skill` so each listed skill carries
    its **bilingual label** (`name`) instead of making every client keep an id→name table.
  - **Bug 3 — bilingual API payloads ignored the caller's language.** `SetLocale` is registered on the
    **web** group only, so on `/api/v1` neither the user's stored `locale` nor Accept-Language ever
    applied — and it could not simply be added to the api group, because group middleware runs before
    `auth:sanctum`, leaving `$request->user()` null. New `App\Support\RequestLocale` resolves the
    documented precedence (`?locale=` → user's stored locale → Accept-Language → default) inside the
    Resource, where the user IS resolved; `SkillResource` and `ProviderSkillResource` both use it.
    Compounding it, **`LocaleService.choose()` only ever persisted to the device** and never called
    `PATCH /me/preferences` (P1-05b), so the app's language toggle and the server's copy drifted apart
    permanently. It now syncs, best-effort. Verified live: toggling to English flipped the stored
    locale `fr`→`en` and the skill chips followed the chrome.
  - 394 backend tests green (2 new), PHPStan L6 + Pint clean, mobile build green, API-client drift gate
    stable, i18n parity 372 × 2, colour + bare-string linters clean.

- **Mobile ↔ API wiring — the provider execution surface is real (check-in / status / report)**. The
  work-detail screen was the last big fixture-only *mutation* surface; it now drives the P5-03/04/06
  endpoints end-to-end.
  - **New read: `GET /provider/work/{engagement}`** — the single round-trip behind the screen.
    Authorised by the **same boundary as the execution Actions** (an active, non-removed assignment),
    so anything the screen can read is something the caller may actually act on; a stranger *and* a
    removed worker both get 403. Post-engagement the worker gets the **exact** site address (the
    coarse-area rule of `JobResource` guards the *pre*-engagement provider), and whether there is an
    address at all is the `EngagementModePolicy`'s call, never an inline mode check.
  - **State is derived, never a stored flag** — a new `WorkProgress` service reads `checked_in` from
    an OPEN `work_session`, `current_status` from the latest execution kind narrated by *this* worker
    in the job's conversation (the chat IS the state machine, doc 06), and `report_submitted` from a
    submitted `job_report`. A read never creates the conversation. So the client can't drift: check
    out and the affordance flips back; `supports_check_in` is false on remote and the button simply
    isn't rendered.
  - **Mobile**: `ApiService` gained `workDetail` / `checkIn` / `checkOut` / `recordStatus` /
    `submitJobReport` (multipart `FormData` with a pass-through `bodySerializer`, so fetch sets its
    own boundary). `ProviderService` remembers which engagement ids the API confirmed — that set is
    the switch routing mutations to the server vs the fixture, so the section stays demoable
    unconnected. Check-in/out attach a **best-effort** GPS fix (short timeout, null on refusal — the
    server accepts a session without coordinates, so a dead GPS never blocks a worker).
  - **A real report composer** replaced the stub button: summary (required client-side, matching the
    server rule), repeatable materials rows, extra charges, and before/after photo pickers — in an
    `ion-modal` styled on the tokens, with the materials grid restacking under 480px. Photos are
    EXIF-stripped server-side (P5-04); the client sends the file as-is.
  - Every mutation runs through one busy-guarded helper that **re-reads the server state either way**
    and surfaces the server's problem+json `detail` on refusal (remote check-in, a session already
    open) rather than a generic error. `resumed` was missing from the status chips and the
    `WorkStatus` type — added.
  - Spec-first as always (OpenAPI → regenerated TS client, drift gate holds). **388 backend tests
    green**, PHPStan L6 + Pint clean, mobile build green, i18n parity 353 keys × 2, no-literal-colour
    + no-bare-string clean.
  - **Verified in a browser against the live API** (`php artisan serve --port=8100` + `ng serve` on
    4200, signed in as the seeded provider Atelier Nkeng): the work list showed the real engagement,
    the detail showed the **exact** site address (proving the post-engagement PII rule), check-in
    flipped the pill to `Arrived` and wrote a real open `work_session` **with a null point** (location
    denied — the deliberate no-GPS path), a status chip wrote `started`, the report sheet submitted a
    real `job_report` with its material and extra charges, and check-out flipped the affordance back
    while the status and report persisted across a full reload. Two defects were found and fixed this
    way — see below.
  - **The remote path was then verified too, and it exposed a real hole.** The remote screen correctly
    showed no Arrival section (`supports_check_in` false, and the server 422s a check-in attempt) —
    but it still offered the **on-site job report**, and `SubmitJobReport` had **no mode gate**, so a
    remote engagement would happily store "before/after photos of the site" for a job with no site.
    Fixed: the doc-06 matrix in `EngagementModePolicy` gains a `job_report` row (onsite/hybrid only),
    `SubmitJobReport` throws `JobReportNotSupported` (422) like `CheckInNotSupported`, and the
    work-detail read now carries `supports_report` + `uses_deliverables` + the engagement's
    `deliverables`. The remote screen renders **Deliverables** instead (P4-08) — a small composer
    (title + optional link) wired to `POST /engagements/{id}/deliverables`, verified end-to-end: the
    row landed with its URL and was narrated into the thread. On-site is unchanged.
  - **Fixed while verifying:** (1) submitting the report reset the summary but not its touched flag,
    so the validation error flashed over the sheet during the dismiss animation; (2) multipart carries
    every field as a string, so materials landed in the jsonb as `"qty": "1"` — `SubmitJobReportRequest`
    now casts them, and a test pins it; (3) a problem+json with an EMPTY `detail` (which
    `check-in-not-supported` sends) produced a **blank** error toast — the client now prefers whichever
    of `detail`/`title` is actually populated and falls back to its own copy, and `acceptOffer` shares
    that helper instead of repeating the bug.
  - **Toolchain:** this machine's PHP had **OPcache commented out entirely** — every request
    recompiled all of Laravel (`artisan serve` ~4s/request, suite 478s). Enabling it (including
    `opcache.enable_cli=1`, which is what `artisan serve`'s cli-server SAPI reads) took requests to
    ~0.25s and the suite to 200s. Dev API port moved to **8100** (8000 taken); `environment.ts`
    follows.
  - **Still fixture-only on the provider side** (both done in the pass above): the Home dashboard and
    the provider's own Profile. Not yet started: Blade public/SEO pages, realtime/media surfaces
    (P4-04..07), native/offline (P5-01/02).

- **Mobile ↔ API wiring — customer discovery loop + provider read surfaces made real**. Continuing the
  method-by-method migration from fixtures to the generated `openapi-fetch` client (every screen keeps
  its fixture as the offline/no-session fallback, so all stay demoable un-connected). This pass:
  - **Workspace chat → real messages** (P4-01/02): the engagement thread loads from
    `GET /jobs/{id}/messages` — free-form text as bubbles, every server-narrated lifecycle kind as a
    neutral system chip; the composer posts free-form text only (rule #11) with a per-send
    Idempotency-Key, then re-fetches. `me().id` from `GET /auth/me` makes authorship real.
  - **Customer discovery loop → real, end-to-end**: an open job's detail offers "Find providers" →
    a new job-scoped shortlist screen from `GET /jobs/{job}/providers` (PII-minimised: headline +
    reputation, no name/precise distance) → the provider profile carrying job context fetches the
    public metrics (P6-12) + published reviews (P6-08) and augments the header from the match resource
    (fields the API withholds — city, member-since, response time, review author — degrade gracefully,
    never faked) → the CTA becomes "Send an offer" → `POST /jobs/{job}/offers` (P2-05).
  - **Three previously-blocked provider READ endpoints built (backend-first), tested, and wired**:
    - `GET /provider/earnings` (P3-07/08) — payable_available (net of reserved pending payouts),
      payable_pending, lead_credits, payout history. Wired the Earnings screen. 4 tests.
    - `GET /provider/opportunities` (P2-05/06) — the provider's live incoming direct offers, each
      embedding its job through `JobResource` (coarse area only, exact address never leaks to a
      pre-engagement provider). `OfferResource` now carries `message` + the whenLoaded `job`. The feed
      + lead detail are real; the lead detail's CTA is **Accept** (`POST /offers/{offer}/accept`,
      P2-06) since a direct offer's correct response is to accept it — a fact gate surfaces the
      server's problem+json `detail`. 4 tests incl. the PII assertion.
    - `GET /provider/work` (P5-03) — the caller's in-flight engagements (job not completed/cancelled),
      newest first; the row id is the ENGAGEMENT id so the work-detail check-in/status/report actions
      target it. Wired the Work list. 4 tests. (The mutations landed in the pass above.)
  - All spec-first (OpenAPI → regenerated TS client, CI drift gate holds); PHPStan L6 + Pint clean;
    mobile builds green; no-literal-colour + no-bare-string + i18n parity (332 keys × 2) all clean.
  - **Still fixture-only on the provider side** (candidates for the next pass): the Home dashboard
    (wallet + stats — composable from earnings + work + own `GET /providers/{party}/metrics`, no new
    endpoint), the work-detail check-in/status/report **mutations** (endpoints exist; wiring needs the
    real engagement id — now available from the work list — plus a thin current-state read), and the
    provider's own Profile (`GET /provider/profile` exists). Not yet started: Blade public/SEO pages,
    realtime/media surfaces (P4-04..07), native/offline (P5-01/02).

- **Ionic app — customer + provider sections built out (fixture-driven, API-shaped)**. All screens are
  standalone Angular 20 components on the generated design tokens; every user string runs through the
  shared i18n source (parity gate ~286 keys × 2), English default with an Account/Welcome FR/EN switch.
  - **i18n was silently broken and is fixed**: ngx-translate v18's `provideTranslateService()` without
    a `loader` registered a no-op loader that shadowed the HTTP loader, so every key rendered raw. Now
    the HTTP loader is passed into `provideTranslateService({ loader })`. (`ng build` green is NOT proof
    a screen renders — verify in a browser; reliable recipe: `npm run build` then serve `mobile/www`
    with a tiny SPA static server on **port 4200**, the port the Chrome extension already has host
    permission for.)
  - **Responsive shell rail fixed (both shells)**: the split-pane side rail was in the DOM but hidden
    because `ion-tabs` defaults to `position:absolute` and painted over it; giving `ion-tabs`
    `position:relative` inside the active pane (≥768px) makes it flow beside the 270px rail.
  - **Onboarding**: Welcome (brand, first-launch FR/EN offer, +237 phone) → Verify (6-cell OTP, resend
    timer, autofocus) → app. `AuthService` (fixture, with requestOtp/verifyOtp seams) + `authGuard`/
    `guestGuard`; Account "Log out" clears the session.
  - **Customer**: Discover → Provider profile (shrinkage rating + sample-floored metrics + double-blind
    reviews) → Request a quote / Post-a-request (conditional-address rule, doc 06) → Jobs → Job detail
    (money/escrow/milestones + approve) → Workspace (chat-as-state-machine, pre-existing).
  - **Provider ("Offer services")**: a tab shell (Home / Opportunities / Work / Earnings) mirroring the
    customer shell; Home dashboard (wallet hero + stats + previews); Opportunities feed → Lead + quote
    composer (SubmitQuotation shape); Work → Work detail (**check-in gated to on-site/hybrid**, status
    chips, submit report — P5-03/04/06); Earnings (payable balance + status-coded payout history).
  - Data still comes from typed fixture services whose shapes match the API, so swapping in the
    generated `openapi-fetch` client is a per-method change. No backend code touched.

- **P3-13 — agreement-time deposit capture → every backend build-plan task now done**:
  `CaptureDepositOnAgreement` collects the deposit (the position-0 milestone) into escrow the moment
  an engagement forms, so a provider knows the money is committed before starting work — rather than
  leaving the customer to fund it manually. It rides the committed `engagement.created` outbox seam
  (`CaptureDepositOnEngagement`, wired in `MoneyServiceProvider::boot`) so the gateway call lands
  **outside** the acceptance transaction (doc 03), and is **idempotent** on a deterministic key
  (`deposit-capture:{engagement}`) so the at-least-once relay never double-charges. Offer-path
  engagements carry no milestones → they capture nothing. 3 tests. PHPStan L6 + Pint clean.

- **Phase 8 (growth & scale) — complete**: P8-01 referrals (codes, qualify-on-first-completed-job,
  **ledger-backed** reward DR platform_revenue / CR promo_liability; self/dup blocked); P8-02 fraud
  controls (weekly velocity → review queue, flagged-not-auto-paid, admin clears); P8-03 dispatch mode
  (rank via ProviderSearch → **fan-out to top N** → **offer-expiry cascade** to the next batch, behind
  a flag); P8-04 bidding behind a `features` flag (off by default); P8-05 one-tap **rebooking** (clone
  last job + direct offer); P8-06 admin analytics (liquidity, match rate, time-to-offer, leakage) via a
  Filament stats widget. Plus the parked P3-11 (auto-approve timer) and P2.5-06 (quote nudges) landed on
  the follow-up engine. Backend **367 green**, PHPStan L6, Pint + design linters clean. **Every
  backend build-plan task is done except P3-13 (agreement-time deposit capture, pairs with the native
  checkout UX).**

- **P7-08 — provider CRM (backend) → Phase 7 backend complete**: `ProviderCustomers` builds the client
  book (job count, completions, lifetime value, last engagement per customer); `ScheduleManualFollowUp`
  lets a provider send a re-engagement nudge on the **same budget + consent gates** (attributed via
  `created_by_user_id`); `do_not_contacts` is **honoured absolutely** — refused at schedule time and
  re-checked at dispatch. `GET /v1/provider/customers` + manual follow-up + do-not-contact endpoints.
  3 tests. Also fixed a Collection-generics PHPStan quirk (return a typed `list`). Backend **350 green**,
  PHPStan L6, Pint clean. **Phase 7 backend is complete (8/8).**

- **P7-05 + P7-06 — WhatsApp channel + routing ladder**: a `WhatsAppSender` rail (Fake/Log,
  config-selected) — templated, deep-linked, in the target's comms locale. `FollowUpDelivery` routes
  each dispatched follow-up to the right transport (push→device token, WhatsApp→phone, SMS→text);
  `ChannelLadder::pick` chooses push if a live device token exists else WhatsApp (the workhorse), used
  by the orchestrator. The follow-up row is always the in-app record. Live WhatsApp template approval
  is the remaining external dep. 3 tests. Backend green, PHPStan L6, Pint clean.

- **P7-02 + P7-07 — event-driven follow-ups + response actions**: `FollowUpOrchestrator` on the outbox
  seam schedules on event and cancels on event (doc 07 rule 1): `engagement.completed` → review_request
  (+2h) + review_reminder (+3d); the customer's `review.submitted` cancels both by dedupe-prefix;
  `warranty.issued` → warranty_expiring 14d pre-expiry. New `CompleteEngagement` action (idempotent).
  Every follow-up carries one **`response_action`**, recorded via `POST /v1/follow-ups/{id}/respond`
  (target-gated); `GET /v1/follow-ups` lists them. 5 tests incl. **complete → 2 scheduled → review →
  both cancelled** and **response_action recorded**. Backend **344 green**, PHPStan L6, Pint clean.

- **P7-01/03/04 — the follow-up engine core**: `follow_ups` + `comms_log`, the retention backbone.
  Scheduling is **idempotent on `dedupe_key`** (`{kind}:{anchor}:{id}:{seq}`) — the same at-least-once
  event 50× yields one row. The dispatcher applies two gates in order: **consent** (marketing kinds
  need the `marketing` grant; service/transactional kinds bypass) then **budget** (per-user per-channel
  rolling caps from `comms_log`; over cap → `suppressed`, not sent). `follow-ups:dispatch` command.
  Marquee tests: **50× → 1 row; 5 SMS → 2 sent / 3 suppressed; revoke marketing → reengagement
  suppressed while check_in_overdue still sends**. Backend **339 green**, PHPStan L6, Pint clean.

- **P6-12 + P6-13 — provider metrics + leakage proxy → Phase 6 complete**: a `ProviderMetrics` service
  with two disciplines. **Sample-size floor** (P6-12): an on-time rate from fewer than 5 data points
  is returned null — "100% on-time (1 job)" is never shown; public `GET /v1/providers/{party}/metrics`
  exposes only the display-safe subset. **Leakage proxy** (P6-13): many completions + few repeat
  customers sets a **flag for a human — flagged, never accused** — surfaced admin-only via a Filament
  `LeakageWatchWidget`, never in the public metrics. 12 tests. Backend **334 green**, PHPStan L6, Pint
  clean. **Phase 6 is now complete (13/13).**

- **P6-11 — warranties + claims + remedy-job spawning**: `warranties` (one per engagement) +
  `warranty_claims`. A claim **spawns a real remedy job** — cloned from the original (`RMD-` ref),
  its own engagement whose **origin is the warranty claim** (a third way into `engagements`, alongside
  offer/quote — added `warranty_claim_id` and widened the origin CHECK), free (agreed 0), with a
  **real lead assignment to the original worker**. Not an email thread; the fix only exists
  on-platform — the anti-leakage payoff. 5 tests. Backend **328 green**, PHPStan L6, Pint clean.

- **P6-10 — dispute flow + admin adjudication**: `disputes` raised by a party (`dispute.raised`
  outbox, never auto-moving money); `AdjudicateDispute` is a human decision that, when it moves money,
  posts a **balanced `Adjustment` ledger transaction stamped with the admin's id** and referenced to
  the dispute — never an edit of history — else resolves with no ledger effect. Written to the audit
  log, attributable to the named admin. Filament dispute queue. 5 tests. Backend **323 green**,
  PHPStan L6, Pint clean.

- **P6-05 + P6-06 — share-my-job + check-in watchdog**: a participant mints an **expiring, revocable
  share link** (`engagement_shares`, token stored hashed) whose public tokenised Blade page (`/s/{token}`)
  shows PII-minimised live status (provider first name, approximate location, on-site/scheduled/
  completed from `work_sessions`) — onsite/hybrid only, stale/revoked → 404. The **check-in watchdog**
  (`safety:check-in-watchdog`) flags a worker past their scheduled start with no `work_session` — a
  `check_in_overdue` alert + staff notice, deduped and mode-gated — reusing the P5-03 audit trail.
  10 tests. Backend **318 green**, PHPStan L6, Pint + both design linters clean.

- **P6-04 — panic button + safety alerts + emergency SMS**: `safety_alerts` + `emergency_contacts`.
  One `POST /v1/safety/panic` raises the alert, **texts every emergency contact directly** (not via
  the relay — a panic must not wait for a queue cadence) and alerts staff via the outbox — all
  server-side, so it **works with the app backgrounded** (the phone only lands one request). Added a
  general `SmsSender` rail (Fake/Log, config-selected, mirroring the P5-05 push rail); the panic copy
  runs through i18n in the user's comms locale. A Filament safety-alert queue (danger badge,
  acknowledge/resolve, admin-attributed). 5 tests. Backend **308 green**, PHPStan L6, Pint clean.

- **P6-08 + P6-09 — double-blind reviews + shrinkage ratings**: `reviews` are two-way and
  **double-blind** — each rests `pending` (content withheld even from an API peek) until BOTH parties
  submit, revealed at once, or the shared 14-day window closes (`reviews:reveal` publishes a lone
  review so a silent no-show can't bury an honest one). The first submission fixes the window; the
  second inherits it, so both sides share one deadline. The displayed rating is a **Bayesian shrinkage
  estimator** (`RatingCalculator`, prior 4.0 × weight 10) recomputed into `provider_profiles` on
  publish: **1×5★ → 4.09 ranks below 200×4.8 → 4.76**. `POST /v1/engagements/{engagement}/reviews`,
  public `GET /v1/providers/{party}/reviews`. 12 tests. Backend **303 green**, PHPStan L6, Pint clean.

- **P6-07 — reports + blocks (honoured in all three paths)**: `reports` (with a first-class
  `off_platform` category — leakage is the core risk) feed the admin queue + a `report.filed` outbox
  alert, never auto-penalising. A `block` is a hard boundary honoured **bidirectionally** and in
  **all three paths, or it isn't a block**: `ProviderSearch` (search + ranking) excludes blocked
  parties, and `CreateDirectOffer` refuses (`PartyBlocked`). `GET/POST/DELETE /v1/blocks`,
  `POST /v1/reports`; a Filament report queue. 6 tests. Backend **297 green**, PHPStan L6, Pint clean.

- **P6-02 + P6-03 — verification review queue + tiers feed the gate**: append-only `activity_logs`
  (DB trigger forbids mutation) + `ActivityLogger`; the signed-URL view controller **logs every view**
  (`verification_document.viewed` with the admin + IP) — the insider-threat control is that *reads*
  are logged, not just edits. `ReviewVerificationDocument` (reviewer-attributed) **raises the party's
  `verification_tier`** to the tier the document grants and invalidates the cached `identity_verified`
  fact, so the accept-paid-job gate becomes data-driven from real approved documents. A Filament
  "Trust & safety" review queue (oldest-pending-first, pending badge, Open/Approve/Reject) routes
  every decision through the Action, never a row edit. 4 tests incl. **tier-1 refused a tier-3 on-site
  job then allowed after approval; remote needs only the lighter check**. Backend **291 green**,
  PHPStan L6, Pint clean.

- **P6-01 — verification documents (encrypted + signed 60s URLs)**: `verification_documents` stored
  in a **separate bucket, encrypted at rest by the app** (Crypt), with the plaintext sha256 recorded.
  The kind fixes the tier it grants, so tier can't be self-assigned. Access is a **signed short-TTL
  app route** (60s), deliberately routed through the app rather than a presigned bucket URL — because
  doc 04 requires *every view* to be logged (P6-02), which a direct bucket link makes impossible.
  `GET`/`POST /v1/verification-documents` (paths never leaked). 5 tests incl. encrypted-at-rest +
  URL-expires-403 + tampered-403. Backend **287 green**, PHPStan L6, Pint clean.

- **P5-05 — push notifications via the outbox**: a provider-agnostic `PushSender` (Fake default +
  FCM HTTP v1 adapter, config-selected — the app never names a transport) and a listener on the
  `OutboxMessagePublished` seam. Push **rides the transactional outbox**, so a notification fires only
  for a committed event: a relayed `message.created` notifies the conversation's participants —
  **never the sender** — on their registered devices, **each in their own comms locale** (`push.*`
  i18n keys). A dead token can't sink the batch. 4 tests. Backend **282 green**, PHPStan L6, Pint
  clean. With P5-03/04/06, the cleanly-backend Phase 5 work is complete; P5-01/02 are the native/
  offline client build.

- **P5-04 — job reports + EXIF-stripped media**: a polymorphic `media` table (the shared attachment
  foundation — job reports now, verification docs + voice notes later) and `job_reports` (summary,
  materials, extra charges, before/after photos). The privacy guarantee lives in `StoreMedia`: raster
  images are **re-encoded through GD, which drops every EXIF/XMP/GPS segment**, and the location the
  client reports is written to the `captured_point` DB column instead — so a customer's photo can be
  served to a provider without leaking the GPS of their home. sha256/bytes describe the clean file.
  `SubmitJobReport` (worker, one txn) behind `POST /v1/engagements/{engagement}/report` (multipart).
  Marquee test: an **injected EXIF marker is absent from the stored bytes** while the geo is recorded
  in the DB. OpenAPI + TS client updated. 4 tests. Backend **278 green**, PHPStan L6, Pint clean.

- **P5-03 + P5-06 — the provider execution surface**: `work_sessions` (geography start/end points +
  GPS accuracy) with a **partial unique index** so a worker can't hold two open sessions on one
  assignment. `CheckIn` opens a session and narrates `arrived` into the workspace thread in its own
  transaction (rule #11); it's gated to onsite/hybrid by `EngagementModePolicy`, so a **remote
  engagement refuses check-in (422)** and exposes no affordance. `CheckOut` row-locks and closes the
  open session — a pure work-time close, kept distinct from declaring the job `completed`.
  `RecordStatus` narrates the structured status signals (on_the_way/started/paused/resumed/completed;
  `arrived` is reserved to check-in). The acting user must be an active assigned worker — the provider
  section only ever queries `assignments`. `POST /v1/engagements/{engagement}/check-in`, `/check-out`,
  `/status`. OpenAPI + TS client updated. 8 tests. Backend **274 green**, PHPStan L6 clean, Pint clean.

- **English is the default language, in both apps.** `APP_LOCALE`/`APP_FALLBACK_LOCALE` are now
  `en` (in `.env`, `.env.example` and the `config/app.php` defaults), and the app's ngx-translate
  default/fallback plus `LocaleService.DEFAULT_LOCALE` match. French is still first-class — it is
  detected from the device/`Accept-Language` and offered, never forced (doc 09 unchanged, only the
  fallback flipped). The admin panel had been rendering in French purely because of `APP_LOCALE`.
- **Responsive app shell: side rail on web, tabs on phones.** `mobile/src/app/tabs/` now wraps the
  `ion-tabs` in an `ion-split-pane` with an `ion-menu` that becomes a permanent 264px side rail at
  ≥768px; the tab bar is hidden at exactly that breakpoint, so the two are never both on screen.
  One `items` array in `TabsPage` drives both, so the rail and the tab bar cannot drift apart.
- **Admin chrome brought onto the tokens** (`backend/public/css/admin.css`, linked from
  `AdminPanelProvider`'s HEAD_END hook next to `tokens.css`). The bespoke dashboard body was already
  correct — verified by rendering the widget in isolation — but it sat inside Filament's stock
  shell, which is why the panel didn't read like the approved proposal. The stylesheet restyles the
  canvas, sidebar (uppercase group labels, brand-tinted active pill on `.fi-active`/
  `[aria-current=page]`, both verified against Filament's own Blade), topbar and page header, and
  strips the widget wrapper's duplicate section chrome. Every value resolves to a `--hm-color-*`
  token — no palette is redeclared.
- **Customer app (Ionic) — first real screens**: a tabs shell (Discover / Jobs / Chats / Account)
  plus the **engagement workspace**, built as standalone Angular 20 components on the generated
  design tokens. Discover has search, an on-site/remote filter, a category rail and provider cards;
  the workspace renders the chat-as-state-machine — free-form bubbles and voice notes interleaved
  with **server-narrated** structured cards (quotation with Accept/Counter, milestone released,
  system chips), with a composer that deliberately has no "submit quote" button (rule #11). Shared
  primitives live in one global stylesheet; every screen is fluid to ~360px with ≥44px tap targets.
  Data comes from a typed fixture service whose shapes match the API, so swapping in the generated
  client is a per-method change. `ng build` green.
- **Token/i18n discipline restored across the admin**: the Filament views had hard-coded hexes and
  English copy (both violations of doc 08/09). They now consume the generated `--hm-color-*` tokens
  (loaded into the panel, with Filament's `.dark` class mirrored onto `data-theme` so both themes
  work) and all admin copy runs through `__()` with a new `admin.*` namespace — **160 i18n keys ×
  2 locales, parity OK**. Also fixed three real gaps in the bare-strings linter (it flagged `@php`
  blocks, nested-paren directives like `@for (x of f(); track y)`, and HTML entities).

- **Filament admin rework — full fidelity**: after the stats/table-widget first pass, rebuilt the
  dashboard as a single bespoke-view widget (`OverviewWidget`) whose Blade view reproduces the
  approved mockup pixel-for-pixel inside the Filament shell — KPI cards with SVG sparklines, a
  severity-striped reconciliation panel, a recent-engagements table (money + status pills + milestone
  progress), and a money-held ledger breakdown. Scoped token CSS synced to Filament's `.dark` class
  for both themes; data computed live (`Ledger::totalByKindMinor`). Clears the admin design-debt item.
  2 widget tests. Backend 266 green, PHPStan L6 clean, Pint clean.

- **P4-08 — deliverables**: `deliverables` table + submit/review. The provider submits an artifact
  (narrated into the thread as `deliverable_submitted`, in-transaction); the customer accepts or
  rejects it with a reason (row-locked, once-only). Provider/customer-gated endpoints. 7 tests.
  This completes the cleanly-backend Phase 4 work; the rest (presence, voice/S3, Ionic workspace,
  reconnect) lands with the app UI. Backend 264 green.

- **P4-03 — engagement channel authorization**: `ChannelAccess::isEngagementParticipant` resolves
  the engagement's job conversation and authorizes only its participants (replacing the P0-16
  deny-by-default stub); non-participants and unknown engagements are rejected. 4 tests. Backend 257 green.

- **P4-01/02/09 — the chat is the state machine**: `conversations` + `messages` (structured
  `message_kind`, payloads, receipts/reactions). Structured messages are narrated by the server via a
  `Narrator` called inside the transition's own transaction — so the chat and the state never diverge
  and a rollback narrates nothing (AcceptQuotation now narrates `quote_accepted`). The message
  endpoint accepts only free-form text; a client posting a structured kind is rejected (422), and only
  participants read/post. Detected phone/email is flagged, not blocked. `GET`/`POST
  /v1/jobs/{job}/messages`. 8 tests. Backend 256 green.

- **P3-09 — nightly reconciliation + exceptions**: `reconcile:nightly` runs the pollers, then
  integrity-checks the ledger (succeeded-intent-missing-ledger, and a `--wallet-cash` vs platform_cash
  settlement mismatch) and records any discrepancy as a `reconciliation_exceptions` row + admin alert
  — never auto-correcting. A partial unique index keeps re-runs from duplicating open exceptions, and
  `ResolveReconciliationException` records a human's balanced adjustment stamped with their user id.
  5 tests. **Phase 3 essentially complete** (P3-11/13 parked for Phase 5). Backend 248 green.

- **P3-15 — cash settlement recording**: cash is a first-class rail. A new `provider_receivable`
  (asset) account kind + `cash_settlements` table; `RecordCashSettlement` books the platform's 15%
  commission as revenue and as a debt the provider owes (DR provider_receivable / CR platform_revenue)
  and marks a named milestone paid — no escrow involved. Provider-gated
  `POST /v1/engagements/{engagement}/cash-settlements`. 3 tests. Backend 243 green.

- **P3-12 — global money property test**: a deterministic 60-step randomized sequence of real flows
  (credit purchase/spend, payable grants, payouts + reversals) asserts SUM(debits) == SUM(credits)
  globally after every step. Backend 240 green.

- **P3-10/14 — escrow release + refund**: collection lands via the escrow intent path; each milestone
  approval (`ApproveMilestone`) releases only its slice from escrow to the provider net of the 15%
  commission — serialised per engagement by an advisory lock, idempotent, and **leaving the remainder
  escrowed** on a partial approval. `RefundEngagement` returns whatever escrow remains.
  `Ledger::escrowHeldMinor` reads per-engagement escrow from the transaction reference. Customer-gated
  `POST /v1/milestones/{milestone}/approve` + `/engagements/{engagement}/refund`. 7 tests. Backend 239
  green, PHPStan L6, Pint clean.

- **P3-08 — payouts + failure reversal**: `RequestPayout` reserves funds via the pending payout row
  (locks the payable account, subtracts already-pending payouts → no double-spend); `ResolvePayout`
  posts DR provider_payable / CR platform_cash only on gateway confirmation (`payouts:reconcile`);
  `ReversePayout` corrects a confirmed-then-failed payout with a new mirror transaction — never a
  delete — restoring provider_payable to its pre-payout value with both the payout and reversal
  transactions intact. `POST /v1/provider/payouts`. 5 tests. Backend 232 green, PHPStan L6, Pint clean.

- **P3-07 — lead credits (purchase + spend)**: purchases arrive through the collection path; the
  `SpendLeadCredits` action shrinks the liability and books revenue, locking the provider's credit
  account row so concurrent spends serialise and the balance can never go negative (`Insufficient
  LeadCredits` carries the shortfall). `GET /v1/provider/credits` reports the balance. 4 tests.
  Backend 227 green, PHPStan L6 clean, Pint clean.

- **P3-06 — reconciliation poller**: `payments:reconcile` sweeps unresolved intents and asks the
  gateway for the authoritative status, so a lost webhook still resolves and a stuck intent past its
  expiry is force-expired. Per-intent reconcile locks the row and applies idempotently (webhook/poll
  converge). 3 tests. Backend 223 green, PHPStan L6 clean, Pint clean.

- **P3-04/05 — payment intents + webhook handler**: `payment_intents` (idempotent on the request
  Idempotency-Key — same key returns the same intent, never a second charge) start a MoMo collection
  and rest in `processing`; the resource is the pending-UX contract (status + expiry countdown +
  payment_url). The webhook (`ProcessPaymentWebhook`) verifies the signature, deduplicates by insert
  into `payment_events` (savepoint-wrapped), then locks the intent and applies the **fetched**
  authoritative status once via `ApplyGatewayResult` (DR gateway_receivable / CR liability). Marquee
  test: **10 duplicate webhooks → exactly one ledger transaction**; plus unsigned → 401 and a
  post-terminal no-op. Public webhook route is idempotency-exempt. OpenAPI + TS client updated.
  Backend 220 tests green, PHPStan L6 clean, Pint clean.

- **P3-03 — payment-gateway abstraction + CinetPay**: the `PaymentGateway` interface and normalised
  DTOs, a CinetPay adapter (collection init + status check, status mapping, HMAC `x-token` webhook
  verification), and an in-memory `FakeGateway` that drives the pending→settled flow deterministically
  for tests/local. The active gateway is chosen by `config('payments.gateway')` (default `fake`) in
  `MoneyServiceProvider`; nothing else in the app names a provider. 7 tests. The live-sandbox
  end-to-end check waits on real CinetPay credentials — everything else (contract, mapping, signature)
  is covered.

- **P3-02 — rebuildable balance cache**: `ledger_balances_cached` materialized view (a derived read
  model), refreshed by `ledger:rebuild-balances`. A test rebuilds it from the entries and asserts the
  cached balance equals the live view and the computed balance, before and after further postings —
  the cache can never silently drift from the truth.

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
- **P0-09**: hosting-region ADR drafted (`docs/adr/0001-hosting-region.md`). *(Superseded — it was
  DECIDED as Option A, in-country, and the P0-09 row above records that. This line is kept only
  because it is part of a dated historical entry.)*
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

- **Payment methods: DECIDED (founder, 2026-09-13) → MTN Mobile Money, Orange Money and cash, only.**
  Implemented the same day: `PaymentMethod` enum, `method` on payment intents and payouts (inferred
  from the operator prefix, overridable), the CinetPay adapter names the operator (`MTNCM`/`OMCM` —
  confirm against the live sandbox), `GET /meta.payment_methods`, a rail choice pre-selected on the
  app's three money sheets, admin columns, public copy. Cash stays the settlement path (P3-12).

- **Error reporting abroad (2026-09-13): Sentry is wired but off.** `SENTRY_DSN` empty means
  the SDK is inert. Setting it sends stack traces, breadcrumbs (log lines, cache keys, SQL
  without bindings) and the request id to Sentry's servers in the EU or US — outside the
  in-country rule of ADR 0001. `send_default_pii` is off and phone numbers are redacted in log
  context, so an event carries no personal data by design; whether "no personal data" satisfies
  the lawyer under Law 2024/017 is the founder's call, with the CNDP register. Alternatives if
  not: self-hosted Sentry (heavy for one box) or GlitchTip (light, same SDK, same DSN), in
  country. Until decided: JSON logs on the box, searchable by `X-Request-Id`.

- **SMS aggregator (2026-09-13): Twilio for now, a local aggregator when price says so.** The
  adapter is Twilio because its API could be written against without guessing and reaches every
  MTN and Orange number; it is also the dearest per text (roughly ten times a Cameroonian
  aggregator's rate). Volume is OTPs, panic alerts and the ladder's last rung — small — so cost
  is not the launch question. When it becomes one, a local aggregator (Nexah, Orange's SMS API,
  or whichever the founder can open an account with) is one more class behind `SmsSender`,
  selected by `SMS_SENDER`; nothing else moves. Needs a founder decision only on the sender id:
  the alphanumeric `HandyMan` (no reply path; what the deploy example assumes) or a purchased
  number people can text back.

- **Primary button label contrast (founder decision, 2026-09-12).** White on the brand red
  `#ec3013` is 3.76:1; WCAG AA wants 4.5:1 for a 14px label and no label colour gets there on
  that red (ink is 3.73). It clears the 3:1 large-text bar only. Options: (a) accept — it is the
  brand, labels are 800-weight, and the checker prints the waiver on every run; (b) move the
  primary FILL to the ramp's 700 step `#ae1800` (passes at 6.3:1, visibly darker, the accent
  stays `#ec3013` for icons/rules/the poster band); (c) make primary labels ≥ 18.66px bold.
  Recommend (a) unless the outdoor-legibility test on a real low-end Android says otherwise —
  that test is on the same checklist and would settle it with evidence rather than a ratio.

- **P0-09 hosting region: DECIDED → in-country (Cameroon)**, Option A. Lawyer sign-off + CNDP
  processing register still pending (founder tasks). Self-managed PostGIS/Redis/MinIO in-country.
- **Ionic flavour: DECIDED → Angular + TypeScript** (ngx-translate for i18n).
- Redis: running as a Windows service on :6379 (confirmed). Using **predis** client (no phpredis
  extension). Note: `php artisan horizon` needs pcntl (not on Windows) — dashboard + config work
  locally; the Horizon supervisor runs on Linux in prod/CI. Local queues: `queue:work redis`.
