# Build State

> Living tracker for the build. Updated as work progresses. Source of truth for **where we
> are** and **how this machine is set up**. Read this first when resuming.

_Last updated: 2026-07-28 (mobile↔API wiring: provider execution surface — check-in / status / report)_

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
| P4-04, P4-05, P4-06, P4-07 | typing/presence, voice notes (S3), Ionic workspace UI, reconnect reconciliation | not started (client/realtime/media — land with the app UI in/around Phase 5) |

### Phase 5 — Execution (provider section + native capabilities)

| ID | Task | Status |
|---|---|---|
| P5-03 + P5-06 | `work_sessions` check-in/out (geo + timestamp) + structured provider status actions | **DONE** — `work_sessions` (geography start/end points + GPS accuracy; `work_sessions_span_check`; **partial unique `one_open_session_per_assignment`** — can't check in twice); `CheckIn` opens a session and narrates `arrived` in-transaction (rule #11), gated to onsite/hybrid via `EngagementModePolicy` (**remote → 422 `check-in-not-supported`**, no affordance); `CheckOut` row-locks and closes the open session (pure work-time close — completion stays a separate signal); `RecordStatus` narrates the structured `ProviderStatus` signals (on_the_way/started/paused/resumed/completed — `arrived` reserved to check-in); acting user must be an active assigned worker (provider section queries `assignments` only, no individual-vs-company branch); `POST /v1/engagements/{engagement}/check-in`, `/check-out`, `/status`; OpenAPI + TS client updated; 8 tests incl. **remote-refuses-check-in, double-check-in 409, arrived-not-postable-via-status**. Later joined by the read side: `GET /v1/provider/work/{engagement}` + `WorkProgress` (checked_in / current_status / report_submitted all DERIVED from the rows the Actions write), authorised by the same active-assignment boundary; 4 tests |
| P5-04 | `job_reports` + before/after `media`, EXIF stripped server-side | **DONE** — polymorphic `media` table (owner party, attachable type/id, kind, sha256, bytes, `captured_point` geography; CHECKs on bytes/type/kind) + `job_reports` (summary, materials jsonb, extra_charges, signature slot); `StoreMedia` **re-encodes raster images through GD to strip every EXIF/XMP/GPS segment**, records the client-reported geo in `captured_point` server-side (never in the file), and stores sha256/bytes of the CLEAN file; `SubmitJobReport` (worker; attaches before/after photos in one txn); `POST /v1/engagements/{engagement}/report` (multipart); OpenAPI + TS client updated; 4 tests incl. **injected-EXIF-marker gone from stored bytes + geo-in-DB** |
| P5-05 | Push notifications (FCM) via outbox | **DONE** — provider-agnostic `PushSender` abstraction + normalised `PushMessage`; `FakePushSender` (records sends, default) + `FcmPushSender` (HTTP v1, one request/token, per-token failure logged not thrown — live delivery pends real project creds); config-selected in `NotificationsServiceProvider` (`config/notifications.php`, default `fake`). Push **rides the transactional outbox**: `NotifyOnOutboxMessage` subscribes to the `OutboxMessagePublished` seam and, for a relayed `message.created`, notifies the conversation's participants **except the sender** on their non-revoked devices, **each in their own comms locale** (`push.*` i18n keys, parity OK). New endpoints: none (server-internal). 4 tests incl. **sender-excluded + per-locale copy + sole-participant no-op** |
| P5-01, P5-02 | Ionic PWA/Android/iOS + secure token storage; offline-first cache (Drift) + write queue | not started (client/native — need device builds) |

**Phase 5's cleanly-backend tasks (P5-03/04/05/06) are done; P5-01/02 are the native/offline client
build, which lands with the app UI work.**

### Phase 6 — Trust, safety, reputation

| ID | Task | Status |
|---|---|---|
| P6-01 | `verification_documents` + encrypted bucket + signed 60s URLs | **DONE** — `verification_documents` (native `doc_kind`/`doc_status`; `grants_tier` fixed by kind so tier can't be self-assigned; reject-reason CHECK) stored in a **separate `verification` disk, encrypted at rest by the app** (`VerificationStorage` via `Crypt` — on-disk bytes are never the plaintext even if the bucket is misconfigured); sha256 of the plaintext recorded. Access is a **signed short-TTL app route** (`SignedDocumentUrl`, 60s; `GET /verification-documents/{document}/view` under `signed` middleware) — deliberately through the app, not a presigned bucket URL, so P6-02 can log every view. `SubmitVerificationDocument`; `GET`/`POST /v1/verification-documents` (paths never returned). OpenAPI + TS client. 5 tests incl. **encrypted-at-rest, signed-URL streams within TTL, expired→403, tampered→403** |
| P6-02 | Filament review queue; every document **view** writes an activity log | **DONE** — append-only `activity_logs` (DB trigger forbids UPDATE/DELETE; actor/action/subject/context/ip) + `ActivityLogger`; the signed-URL **view controller logs every view** (`verification_document.viewed`, capturing the admin + IP) — reads, not just edits (the insider-threat control). Filament `VerificationDocumentResource` under a "Trust & safety" nav group: oldest-pending-first queue, pending badge, Open-document (signed URL) / Approve / Reject actions routing through the domain Action (never a row edit). 1 test (view-is-logged) |
| P6-03 | Verification tiers feed `identity_verified`; `AcceptPaidJob` reads tier from job mode + skill `risk_tier` | **DONE** — `ReviewVerificationDocument` (approve/reject, reviewer-attributed, once-only) **raises the party's `verification_tier` to the tier the document grants** and invalidates the cached `identity_verified` fact; the gate (already mode+risk-keyed) now becomes fully data-driven from real approved documents. 3 tests incl. **tier-1 provider refused a tier-3 on-site job, allowed after approval; a remote high-risk job needs only the lighter check** |
| P6-07 | `reports` + `blocks`; blocks honoured in search, ranking, offers | **DONE** — `reports` (category incl. first-class `off_platform`; not-self CHECK; feeds admin queue + `report.filed` outbox alert, never auto-penalises) + `blocks` (composite PK, not-self CHECK). `Block::partyIdsAround`/`existsBetween` honour a block **bidirectionally**; wired into **all three paths** — `ProviderSearch` (search + ranking) excludes blocked parties, `CreateDirectOffer` refuses (`PartyBlocked` 422). `BlockParty`/`UnblockParty`/`ReportParty`; `GET/POST/DELETE /v1/blocks`, `POST /v1/reports`; Filament report queue. OpenAPI + TS client. 6 tests incl. **block honoured in search (either direction) + offer refused** |
| P6-08 | Reviews: double-blind, 14-day window, simultaneous reveal | **DONE** — `reviews` (native `review_visibility`; UNIQUE(engagement, author); not-self + 1–5 CHECKs; `private_note` never published). `SubmitReview` rests each review `pending` — content withheld even from an API peek — until BOTH parties submit (revealed at once) or the shared window closes; the first submission fixes `window_closes_at`, the second inherits it. `RevealDueReviews` + `reviews:reveal` command publish a lone review when its 14-day window expires. `POST /v1/engagements/{engagement}/reviews`; public `GET /v1/providers/{party}/reviews` (published only). OpenAPI + TS client. Tests incl. **hidden-until-both, window-expiry reveal, dup 409, non-party 403** |
| P6-09 | Bayesian shrinkage rating display | **DONE** — `RatingCalculator` shrinks toward the prior mean (4.0, weight 10 pseudo-reviews): `(w·mean + Σ)/(w + n)`; recomputed into `provider_profiles.rating_avg` (shrunk) + `rating_count` (RAW, for P6-12's sample-size floor) on every publish. Test: **1×5★ → 4.09 ranks below 200×4.8 → 4.76; unrated shows null, not the bare prior** |
| P6-04 | Panic button + `safety_alerts` + emergency contact SMS + admin alert | **DONE** — `safety_alerts` (native `safety_alert_kind`; geo point; status open/acknowledged/resolved, resolution attributed to a named admin) + `emergency_contacts` (citext phone). `RaisePanicAlert` (one request) creates the alert, **texts every emergency contact directly** (not via the relay — a panic mustn't wait for a queue) and alerts staff via `safety.alert_raised` outbox — all server-side, so it **works with the app backgrounded**. New `SmsSender` rail (Fake/Log, config-selected — mirrors the push rail); panic SMS copy through i18n (`sms.panic_alert`, per comms locale). `POST /v1/safety/panic`, `GET/POST/DELETE /v1/emergency-contacts`; Filament safety-alert queue (danger badge, acknowledge/resolve). OpenAPI + TS client. 5 tests incl. **all contacts texted + staff alerted + no-contacts still records** |
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
| P7-07 | `quote_pending_customer` / `warranty_expiring` / `review_request` / `maintenance_due` + `response_action` | **DONE (core)** — review_request/reminder + warranty_expiring wired to events (above); every follow-up carries a single **`response_action`** recorded via `POST /v1/follow-ups/{followUp}/respond` (target-gated, enum'd actions), `GET /v1/follow-ups` lists a user's nudges. quote_pending_customer / maintenance_due scheduling lands when their source events are wired. Test: **response_action recorded → status responded; non-target → 403** |
| P7-05 | WhatsApp Business API + approved templates + deep links | **DONE (adapter)** — `WhatsAppSender` rail (Fake/Log, config-selected, mirroring push/SMS); template = kind, variables + **deep link back to the follow-up**, sent in the target's **comms locale** (`followup.*` i18n copy, parity OK). `FollowUpDelivery` routes each follow-up to the right transport at dispatch; a transport failure marks the row `failed`. Live template approval is the remaining external dependency (like CinetPay creds). Test: **WhatsApp follow-up → transport got template + fr locale + deep link** |
| P7-06 | Channel ladder in_app → push → whatsapp → sms → email | **DONE** — `ChannelLadder::pick` chooses the outbound channel (push if a live device token, else WhatsApp — the workhorse), used by the orchestrator; the follow-up row is always the in-app record; SMS/email reserved (SMS transactional, email for receipts). Test: **ladder picks push with a token, WhatsApp without; push follow-up reaches the device token** |
| P7-07 | `quote_pending_customer` / `warranty_expiring` / `review_request` / `maintenance_due` + `response_action` | pending |
| P7-08 | Provider CRM surface (customer list, pipeline, manual follow-up, do-not-contact) | **DONE (backend)** — `ProviderCustomers` builds the client book (per customer: job count, completions, lifetime value, last engagement) from the provider's engagements; `ScheduleManualFollowUp` lets a provider send a `reengagement` nudge on the **same budget + consent gates** (`created_by_user_id` recorded) — a provider can't spam through the platform; `do_not_contacts` (per provider→customer) is **honoured absolutely** — refused at schedule time and re-checked at dispatch. `GET /v1/provider/customers`, `POST /v1/provider/customers/{party}/follow-up`, `POST`/`DELETE .../do-not-contact`. 3 tests. (Pipeline view is a client/admin UI surface.) |

**Phase 7 complete (backend): 8/8 tasks. P7-05 live WhatsApp templates + the pipeline UI are the external/UI remainders.**

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

**Every backend build-plan task is now done** (P3-13 — agreement-time deposit capture — was the last one). What remains is the client/native/realtime UI work: the Ionic PWA/Android/iOS build + offline write queue (P5-01/02), the engagement-workspace realtime/media surfaces (P4-04..07), and the public/SEO Blade pages — plus external dependencies awaiting real credentials (CinetPay live sandbox, FCM project, WhatsApp template approval).

## Design debt (tracked)

- **UI quality bar (user-mandated): every UI must be beautiful, professional, perfect.** New UI is
  built to that bar from the start on the design-token system (light+dark, semantic colours,
  no-literal-colour lint).
  - **Filament admin — reworked to full visual fidelity (proposal approved).** The dashboard is a
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
  - **Ionic app — customer + provider sections substantially built** (see the top "what was done"
    entry). Both are token-driven (light+dark, no-literal-colour + no-bare-string linters clean),
    English-default with a working FR/EN switch, and the responsive shell now really shows a side
    rail on web (the split-pane `ion-tabs` overlap bug was fixed) and a tab bar on phones.
  - **Still owed:** Blade public/SEO pages; native/offline (Capacitor build, secure token storage,
    Drift offline cache — P5-01/02); wiring the fixture services to the generated API client; and
    real-time/media surfaces (voice notes, presence, reconnect — P4-04..07).

## What was done, most recent first

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
