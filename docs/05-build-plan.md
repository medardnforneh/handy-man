# 05 — Build plan

Phases are dependency-ordered, not preference-ordered. You cannot write the escrow release
handler before `engagements` exists, because release fires on an engagement transition. Each
phase ends with something demonstrable.

Task IDs are stable — reference them in commits (`feat(P2-04): ...`).

---

## Frontend decisions

Resolved in `docs/08-frontend-architecture.md`. Summary:

- **Public web**: Blade + Alpine + Tailwind — SEO surface, must work without the app
- **The app**: **Ionic 8 + Capacitor 6 (Vue or Angular + TS) → PWA + Android + iOS from ONE
  codebase.** No separate customer/provider apps — one app, both sections always visible
  (doc 10)
- **Admin**: Filament 5 — web only, never mobile, behind a staff/`superadmin` role
- **Contracts**: OpenAPI 3.1 → TypeScript client, generated in CI. Never hand-written.
- **Tokens**: `tokens.json` → Tailwind + Ionic CSS vars + Filament. Semantic names only.

Read doc 08 before writing any frontend code (Ionic chosen because it's the builder's mastered
stack; Flutter kept as the documented alternative with a switch trigger) and doc 10 before any
authorization code (open navigation, fact-gated actions).

## Phase 0 — Foundations (no features)

| ID | Task | Acceptance |
|---|---|---|
| P0-01 | Laravel 13 skeleton, PHP 8.3, Pest, Pint, PHPStan L6, CI | `composer test` and `composer analyse` green in CI |
| P0-02 | Postgres 16 + PostGIS + citext + pg_trgm via Docker Compose | `SELECT PostGIS_Version()` works in test suite |
| P0-03 | Redis + Horizon | Horizon dashboard reachable, protected |
| P0-04 | `app/Support/Money.php` — minor units, currency, sign convention documented | Unit tests; no float anywhere |
| P0-05 | Base Action / Resource / Policy conventions + one worked vertical slice | Documented in CLAUDE.md, one example merged |
| P0-06 | Idempotency middleware + `idempotency_keys` | Replayed key returns stored response, does not re-execute |
| P0-07 | Transactional outbox + relay worker | Test: rolled-back txn publishes nothing |
| P0-08 | `/api/v1` scaffold, `app_version` header, force-update kill switch | Old version header → 426 Upgrade Required |
| P0-09 | **Decide hosting region** (doc 04 §4.3) | Written decision + rationale in `docs/adr/0001-hosting-region.md` |
| P0-10 | OpenAPI 3.1 spec + CI codegen → TypeScript client | Hand-written client model fails CI |
| P0-11 | `tokens.json` → Tailwind + Ionic CSS variables + Filament theme | One token change updates every surface |
| P0-12 | Blade public scaffold + Ionic app shell (Capacitor web/Android/iOS build); Reverb + auth'd channels | Public page crawlable; one codebase builds all three targets; private channel rejects non-participants |
| P0-13 | **Dark + light themes wired from `tokens.json` across all surfaces** | Flipping one token updates Blade, the Ionic app, and Filament, or CI fails |
| P0-14 | Lint rule: no literal colour references in components | A hard-coded hex fails the build |
| P0-15 | i18n scaffold: shared FR/EN source → Laravel lang + app i18n JSON (vue-i18n/ngx-translate); missing-key CI gate | A key missing in either language fails the build |
| P0-16 | Lint rule: no hard-coded user-visible strings | A bare string literal in a view/component fails the build |
| P0-17 | **Access-model foundation** (doc 10): capability/guard base, `precondition_unmet` response shape, fact derivation + cache | A guarded action with an unmet fact returns `missing_fact` + `resolve` deep link, not a 403 |
| P0-18 | Spatie scoped to org-internal + staff/admin roles only | No role check gates the customer/provider section split |

> P0-09 is a blocker, not a chore. Everything after it writes personal data to disk.
>
> **Start these external dependencies on day 1** — they sit in human queues and will gate
> launch: MTN MoMo API KYC, aggregator merchant onboarding, WhatsApp Business template
> approval, CNDP processing authorisation.

## Phase 1 — Identity and catalog

| ID | Task | Acceptance |
|---|---|---|
| P1-01 | `parties`, `users`, `organizations`, `memberships` + kind-enforcing triggers | Cannot attach a user to an org-kind party |
| P1-02 | OTP signup/login, `otp_challenges`, rate limits (phone/IP/device) | Pest test proves 4th OTP in an hour is rejected |
| P1-03 | Sanctum access tokens (15m) + rotating refresh tokens + reuse detection | Test: replaying a rotated token revokes the whole family |
| P1-04 | `devices` registration, push token capture | |
| P1-05 | `consents` — granular, versioned, revocable, **+ `presented_locale`** | Revoking `location_tracking` blocks geo writes; consent records the language shown |
| P1-05b | `users.locale` + `users.comms_locale` + first-launch language detection/offer | Anglophone user is offered EN, not silently defaulted to FR |
| P1-06 | `addresses` + PostGIS, GIST index | `ST_DWithin` query under 50ms on 100k seeded rows |
| P1-07 | `skills` taxonomy, bilingual (fr/en), seeder with a real Cameroon trade list | ~40 leaf skills across ~10 categories, both languages populated |
| P1-07b | `translations` table (or paired columns) for catalog entities; language-tagged user text | FTS uses the matching `french`/`english` config per text |
| P1-08 | `provider_profiles`, `provider_skills`, `service_areas` | |
| P1-09 | Filament 5 panel, 2FA mandatory, admin roles | Cannot reach `/admin` without 2FA enrolled |
| P1-10 | DSAR export + crypto-shred erasure path | Test: erasure destroys key, ledger FKs survive |

**Demo at end of P1:** a provider signs up by phone, lists skills, sets a service radius; an
admin sees them in Filament.

## Phase 2 — Jobs, offers, engagements (direct booking only)

| ID | Task | Acceptance |
|---|---|---|
| P2-01 | `jobs` + `engagement_mode` + conditional-address CHECK + `JobStateMachine` | Remote job saves with NULL address; onsite job without address is rejected **by the DB** |
| P2-02 | `EngagementModePolicy` — feature applicability object (doc 06) | No `if ($mode === 'remote')` anywhere outside this class |
| P2-03 | Job creation, photos, PII-minimised `JobResource::forViewer()` | Pre-engagement viewer never sees exact address |
| P2-04 | Provider search — `ST_DWithin` + skill + rating + tier; **skips geo for remote** | Remote search returns providers outside any radius |
| P2-05 | `job_offers` + `origin=customer_direct` + expiry job | |
| P2-06 | **`AcceptOfferAction`** — `lockForUpdate` on job, supersede siblings, outbox | **Concurrency test: 20 parallel accepts → exactly 1 engagement** |
| P2-06b | **`AcceptPaidJob` capability gates on `identity_verified` keyed to `engagement_mode`** (doc 10) | On-site accept without verification → `precondition_unmet`; remote accept under lighter check succeeds |
| P2-07 | `engagements` + auto-assign for individual providers | Individual provider engagement auto-creates exactly 1 `lead` assignment |
| P2-08 | `assignments` + dispatcher authorisation (org boundary) | Dispatcher of org A cannot assign a worker of org B |
| P2-09 | Availability rules + conflict detection | Cannot double-book a worker in overlapping windows |
| P2-10 | Filament: jobs, offers, engagements, manual reassignment | |

**Demo at end of P2:** a customer books a named provider for an on-site job and a remote
consultation; a company dispatcher assigns two staff; neither company can touch the other's jobs.

## Phase 2.5 — Negotiation and quotations

New, and it precedes money because the money flows are shaped by it.

| ID | Task | Acceptance |
|---|---|---|
| P2.5-01 | `quotations` + `quotation_lines`, versioned + immutable | **`UPDATE` on a submitted quote throws**; revision creates v2 with `supersedes_id` |
| P2.5-02 | Partial unique index: one live quote per provider per job | Second draft rejected by the DB |
| P2.5-03 | The three dates: requested / estimated / committed (doc 06) | `on_time_rate` computed against `committed` only |
| P2.5-04 | `site_visits`, chargeable + creditable against the final quote | Visit fee deducted on acceptance |
| P2.5-05 | Quote accept → `engagements` + `milestones` | **Deferred trigger: `SUM(milestones) = agreed_amount`** |
| P2.5-06 | `quote_expiring` / `quote_pending_customer` follow-ups | Cancelled on any response |

## Phase 3 — Money

Ledger before payments. Always. (Full detail: `docs/03`.)

| ID | Task | Acceptance |
|---|---|---|
| P3-01 | `ledger_accounts/transactions/entries` + balance trigger + REVOKE UPDATE/DELETE | **Test: `UPDATE ledger_entries` raises at DB level** |
| P3-02 | `ledger_balances` view + rebuild-from-entries test | Cached balance == recomputed balance |
| P3-03 | `PaymentGateway` interface + one aggregator impl (CamPay or CinetPay) | Sandbox collection succeeds end-to-end |
| P3-04 | `payment_intents` + USSD-push flow + pending UX contract | |
| P3-05 | Webhook handler: signature-verify → dedupe-by-insert → locked apply | **Test: 10 duplicate webhooks → 1 ledger transaction** |
| P3-06 | Reconciliation poller with backoff; timeout → `expired` | Test: lost webhook still resolves via poll |
| P3-07 | Lead credits: purchase + spend flows | |
| P3-08 | Payouts + failure reversal (new balanced txn, never a delete) | |
| P3-09 | Nightly reconciliation + `reconciliation_exceptions` + admin alert | |
| P3-10 | Escrow: collection, release-on-complete, refund | |
| P3-11 | Auto-approve timer (72h) `work_submitted → completed` | |
| P3-12 | Property test: global debits == credits over random flow sequences | |

| P3-13 | Deposit = milestone position 0; capture on agreement | |
| P3-14 | Milestone approval releases its slice from escrow | Partial release leaves the remainder escrowed |
| P3-15 | **Cash settlement recording** | Provider records cash; ledger reflects it; commission raised as a fee |

> Cash is a first-class rail here (vision doc), not a rounding error. Recording it honestly is
> what lets you *measure* leakage instead of guessing. Make self-reporting strictly better for
> the provider than hiding: it builds their on-platform history, completion rate, and warranty
> coverage.

**Demo at end of P3:** a provider buys credits with real MoMo in sandbox; an escrowed job with
three milestones releases each slice on approval; the nightly reconciliation reports clean.

## Phase 4 — The engagement workspace

The centrepiece (`docs/06`). Build the chat first — it *is* the state machine UI.

| ID | Task | Acceptance |
|---|---|---|
| P4-01 | `conversations`, `messages` + `kind`/`payload`, receipts, reactions | |
| P4-02 | **Structured messages emitted server-side by Actions, in-transaction, via outbox** | Client POST of `quote_accepted` is rejected; rolled-back txn narrates nothing |
| P4-03 | Reverb channels `private-engagement.{id}` + participant Policy | Non-participant subscribe rejected |
| P4-04 | Typing / presence — broadcast only, never persisted | No typing rows in the DB |
| P4-05 | Voice notes (Opus), presigned direct-to-S3 | API server never proxies media bytes |
| P4-06 | Ionic workspace: **virtualized** message list, optimistic sends, inline quote cards | 5,000-message thread stays smooth on a $70 Android (the WebView hot spot — doc 08) |
| P4-07 | **Reconnect reconciliation** — REST refetch is authoritative | Kill the socket mid-session → state converges after refetch |
| P4-08 | `deliverables` submit/accept/reject (remote path) | |
| P4-09 | Contact detection in messages — log, do not block | |

## Phase 5 — Execution (provider section + native capabilities)

| ID | Task | Acceptance |
|---|---|---|
| P5-01 | Ionic app: one codebase → PWA + Android + iOS, Capacitor plugins (camera/geo/push/preferences/sqlite), secure token storage, generated TS client | One codebase builds all three targets; refresh token in OS secure store |
| P5-02 | Offline-first cache (Drift) + write queue with idempotency keys | Airplane mode → queued actions replay once, not twice |
| P5-03 | `work_sessions` check-in/out: geo + timestamp + photo (onsite/hybrid only) | Remote engagement exposes no check-in affordance |
| P5-04 | `job_reports` + before/after `media`, EXIF stripped server-side | |
| P5-05 | Push notifications (FCM) via outbox | |
| P5-06 | Structured actions in the provider section: on-the-way / arrived / started / paused / completed | Each emits the matching message kind |

## Phase 6 — Trust, safety, reputation

| ID | Task | Acceptance |
|---|---|---|
| P6-01 | `verification_documents` + encrypted bucket + signed 60s URLs | Test: URL expires |
| P6-02 | Filament review queue; **every document view writes an activity log** | Test: read is logged, not just write |
| P6-03 | Verification tiers feed the `identity_verified` fact; `AcceptPaidJob` reads tier from job mode + skill `risk_tier` (doc 10) | Tier-1 provider cannot accept a tier-3 on-site job; remote job needs only the lighter check |
| P6-04 | Panic button + `safety_alerts` + emergency contact SMS + admin alert | Works with app backgrounded |
| P6-05 | Share-my-job signed expiring link | |
| P6-06 | Check-in-overdue watchdog | |
| P6-07 | `reports` + `blocks`; blocks honoured in search, ranking, offers | Test all three paths |
| P6-08 | Reviews: double-blind, 14-day window, simultaneous reveal | Test: neither visible until both submit or window expires |
| P6-09 | Bayesian shrinkage rating display | 1×5★ does not outrank 200×4.8 |
| P6-10 | Dispute flow + admin adjudication → balanced adjustment txn | Every adjudication attributable to a named admin |
| P6-11 | `warranties` + `warranty_claims` + **remedy job spawning** | Claim creates a linked job with a real assignment |
| P6-12 | `provider_metrics` — 90-day rolling, sample-size floor ~5 | "100% on-time (1 job)" is not displayed |
| P6-13 | `repeat_customer_rate` surfaced to admin as a **leakage proxy** | High completion + low repeat gets flagged, not accused |

## Phase 7 — Client follow-ups

`docs/07`. Do not defer this — it's where retention lives, and the unconverted-quote nudge is
the highest-ROI message you will ship.

| ID | Task | Acceptance |
|---|---|---|
| P7-01 | `follow_ups` + `dedupe_key` UNIQUE + scheduler | Same event 50× → exactly 1 row |
| P7-02 | Event-driven cancellation | Review submitted → both review follow-ups `cancelled` |
| P7-03 | `comms_log` + per-user per-channel budget | 5 SMS/day → 2 sent, 3 `suppressed` |
| P7-04 | Consent gate on non-transactional kinds | Revoke `marketing` → `reengagement` suppressed, `check_in_overdue` still sent |
| P7-05 | **WhatsApp Business API** + approved templates (**FR + EN, both approved**) + deep links | Reminder arrives in the user's `comms_locale`; template deep-links into the workspace |
| P7-06 | Channel ladder: in_app → push → whatsapp → sms → email | |
| P7-07 | `quote_pending_customer`, `warranty_expiring`, `review_request`, `maintenance_due` | Each has exactly one response action; `response_action` recorded |
| P7-08 | Provider CRM surface: customer list, pipeline, manual follow-up, do-not-contact | Manual follow-ups obey the same budget |

## Phase 8 — Growth and scale

| ID | Task | Acceptance |
|---|---|---|
| P8-01 | Referrals: codes, qualify-on-first-completed-paid-job, ledger-backed | Self-referral and duplicate-phone blocked |
| P8-02 | Referral fraud controls: velocity, device fingerprint, review queue | |
| P8-03 | Dispatch mode: ranking engine, fan-out, offer expiry cascade | |
| P8-04 | Bidding mode | Behind a feature flag; off by default |
| P8-05 | Rebooking a known provider in one tap | |
| P8-06 | Admin analytics: liquidity, match rate, time-to-offer, leakage proxies | |

> Enable P8-03 and P8-04 by config only when supply density supports them (doc 01 §4).

---

## API conventions

- `/api/v1` — additive-only, forever.
- Auth: `Authorization: Bearer <sanctum-token>`; refresh at `POST /v1/auth/refresh`.
- Every mutating request: `Idempotency-Key: <uuid>`.
- Every request: `X-App-Version: 1.4.2`, `X-Device-Id: <uuid>`.
- Errors: RFC 7807 problem+json. Stable machine-readable `type`, human `detail`, and a
  `trace_id` the support team can search.
- Pagination: cursor, not offset. Offset pagination over a growing job feed produces duplicates.
- Money in responses: **always** `{"amount_minor": 20000, "currency": "XAF"}`. Never a
  pre-formatted string, never a float.
- Timestamps: ISO-8601 UTC with offset. Client localises.
- `Accept-Language: fr|en` honoured for all user-facing strings.

## Testing floor

- Pest. Feature tests hit real Postgres+PostGIS, not SQLite. SQLite has no PostGIS, no citext,
  no native enums, no deferred constraint triggers — testing on it tests a different app.
- Every state machine: full transition matrix, including illegal ones.
- Every money flow: balance assertion.
- Three named concurrency tests, non-negotiable:
  1. Parallel offer accepts → one engagement.
  2. Duplicate webhooks → one ledger transaction.
  3. Parallel payout requests → one payout.
- Factories for every model. Seeders producing a realistic Yaoundé dataset (real quarters, real
  coordinates) — dispatch ranking tested against uniformly random points is not tested.

## Definition of ready to launch

**Legal / external** (long lead times — start on day 1)
- [ ] Hosting region decision made and documented (P0-09)
- [ ] CNDP processing register written; authorisation applied for
- [ ] Lawyer has reviewed the privacy policy and consent flows against Law No. 2024/017
- [ ] MTN MoMo KYC complete; aggregator merchant account live
- [ ] WhatsApp Business templates approved

**Money**
- [ ] Reconciliation runs clean for 7 consecutive days in staging
- [ ] Payout tested with real money to a real MoMo number, and reversed
- [ ] `UPDATE ledger_entries` proven to fail at the DB level in production config
- [ ] Milestone sums proven to equal engagement totals under a fuzz test

**Safety**
- [ ] Admin 2FA enforced; document *reads* logged
- [ ] Panic button tested on a physical low-end Android (Tecno/Infinix) with the app backgrounded
- [ ] Force-update kill switch tested against a real old build

**Access model (doc 10)**
- [ ] A brand-new account sees BOTH sections fully — no role gate, no unlock, no mode switch
- [ ] A new user can start a provider profile and list a skill with zero prior grants
- [ ] Accepting an on-site paid job is blocked until verified, returning `precondition_unmet` inline
- [ ] Accepting a remote paid job works under the lighter identity check
- [ ] No Spatie role anywhere gates the customer/provider section split

**Product**
- [ ] A remote engagement completes end-to-end with no address, no check-in, no panic affordance
- [ ] A quote is revised three times; all versions visible; none mutated
- [ ] Workspace state converges after a hard socket kill mid-session
- [ ] Follow-ups: complete a job, submit a review, assert both review follow-ups cancelled
- [ ] `pro` app performs a check-in with zero connectivity and syncs exactly once on reconnect
- [ ] Public discovery pages indexable; Lighthouse ≥ 90 on a throttled 3G profile
- [ ] **A customer can find a provider and request a quote without loading the app bundle** (Blade)
- [ ] The Ionic app builds and runs as PWA, Android, and iOS from one codebase
- [ ] Workspace tested on a real $70-class Android — smooth after virtualization (doc 08 hot spot)
- [ ] Both themes pass WCAG AA contrast, verified independently — not by inversion
- [ ] `pro` app legibility tested outdoors at midday on a physical low-end Android
- [ ] Mobile-browser share of customer traffic instrumented (the doc 08 switch trigger)

**Bilingual**
- [ ] No raw i18n keys reachable in either language (CI gate green)
- [ ] Key screens laid out and verified in French (the longer strings)
- [ ] Terms, privacy, and consent prompts reviewed by the lawyer in **both** FR and EN
- [ ] WhatsApp templates approved in both languages
- [ ] Round-trip: `locale=en` + `comms_locale=fr` → English UI, French reminder
