# 04 — Security, trust, and safety

You flagged this as the most important part. It splits into four distinct problems that get
conflated: **physical safety**, **transactional trust**, **application security**, and **legal
compliance**. They need different solutions.

---

## 1. Physical safety — both directions

The standard mistake is treating this as "verify the worker so the customer is safe." Survey
data on gig workers says otherwise. Asked what platforms should do for their physical safety,
workers wanted:

- Verify the **customer's** identity before they use the service — 55%
- In-app safety features: location tracking, trusted contacts, emergency assistance — 52%
- Remove customers from the platform for bad behaviour or low ratings — 51%

And on fraud: 30% said they'd switch platforms after an identity-theft incident; another 24%
would leave gig work entirely. Safety is a supply-retention feature, not a compliance checkbox.

So: **customers get verified too.** Not to the same tier, but the asymmetry must be defensible.
A provider walking into a stranger's home at 19:00 deserves to know the platform knows who
lives there.

### Verification tiers

| Tier | Requirements | Unlocks |
|---|---|---|
| 0 | Phone OTP verified | Browse, post a job |
| 1 | + Name, photo, one address | Book a tier-1 (low-risk) job; receive direct offers |
| 2 | + National ID (front/back) + selfie liveness, admin-reviewed | Provide services; risk_tier 1–2 skills |
| 3 | + Trade licence / insurance cert; org: RCCM + NIU | risk_tier 3 skills (electrical, gas); company provider status |

`skills.risk_tier` drives the required tier. `jobs.requires_verified_provider` lets a customer
demand tier 2+ regardless.

**Customers** must reach tier 1 before booking, and tier 2 before booking anything outside
daylight hours or above a value threshold. Make that rule explicit and configurable — you'll
tune it.

> **There is no Checkr here.** Checkr, Yardstik, and Accurate run the US gig background-check
> market via API. None operate in Cameroon. Criminal-record checks are not API-accessible.
> Accept it: verification = document capture + selfie match + **human admin review** in
> Filament. Budget for the review queue as an operational cost, and design the Filament
> resource for speed — reviewer throughput is your onboarding bottleneck.
>
> Do **not** claim "background checked" in marketing. You are doing identity verification. Say
> that. Overclaiming here is both a legal and a trust liability.

### In-app safety features (build in this order)

1. **Check-in / check-out** with geo + timestamp + photo, stored in `work_sessions` and `media`.
   This is the audit trail that resolves 80% of disputes and deters most bad behaviour, on both
   sides, simply by existing.
2. **Panic button** in the app's provider *and* customer sections. One tap writes a
   `safety_alerts` row with live location, pushes to platform admins, and SMSes the user's
   `emergency_contacts`. Must work with the app backgrounded.
3. **Share-my-job**: a signed, expiring public link showing job location, counterparty first
   name, and ETA. Send to a family member. Cheap to build, disproportionately reassuring.
4. **Check-in overdue** watchdog: worker marked `en_route` but no `on_site` within N minutes →
   automated check-in prompt → escalate to `safety_alerts` if unanswered.
5. **Report + block**, honoured in dispatch ranking, search, and offer creation. All three.
6. **Two-way rating with removal teeth.** A customer below a rating floor stops receiving
   dispatch offers. Publish this policy — the deterrent only works if it's known.

### Number masking — be realistic

Masked calling via a voice proxy is the ideal. In Cameroon it's expensive and operationally
awkward. Pragmatic sequence:

- v1: in-app chat only. Contact-sharing **detected and logged**, not blocked (see doc 01 —
  blocking at low liquidity kills matches).
- v2: reveal the counterparty's real number only *after* an engagement exists, and log the
  reveal.
- v3: masked calling once volume justifies the per-minute cost.

---

## 2. Transactional trust

- **Escrow** as opt-in Protected Payment (doc 03).
- **Dispute flow**: `work_submitted → disputed` freezes escrow; admin adjudicates in Filament;
  resolution writes a balanced ledger transaction with `created_by_user_id` set. Every
  adjudication is attributable to a named human, forever.
- **Photo evidence**: before/after photos on `job_reports` are the dispute substrate. Make them
  mandatory for escrowed jobs.
- **Auto-approve timer** (72h): protects providers from customers who simply vanish.
- **Reviews** double-blind (doc 02) so retaliation doesn't suppress honest signal.

---

## 3. Application security

### Auth

Sanctum personal access tokens are simple but have no rotation and no refresh — for a platform
holding money on mobile clients, that's insufficient. Passport gives you OAuth2 refresh tokens
but drags in a lot of machinery you won't use.

**Recommended: Sanctum for short-lived access tokens + a hand-rolled rotating refresh token.**

- Access token: Sanctum, **15 minutes**, sent as `Authorization: Bearer`.
- Refresh token: opaque 256-bit random, **stored hashed** in `refresh_tokens`, 30-day expiry,
  kept in the OS secure store via `@capacitor/preferences` (Keychain / Keystore) — never in
  `localStorage` or plain WebView storage.
- **Rotation with reuse detection.** Each refresh issues a new token in the same `family_id`
  and sets `replaced_by_id` on the old one. If a *already-replaced* token is presented, that
  means it was stolen and replayed → **revoke the entire family** and force re-login. This is
  the single highest-value auth control on the list.
- Laravel 13 ships native passkey support. Good for the Filament admin panel. Not yet
  realistic as the primary factor for low-end Android in this market.

httpOnly cookies don't exist on mobile — so you end up with two auth surfaces regardless. Keep
the split clean and deliberate:

- **Ionic app — all three targets (PWA, Android, iOS)** → the same Sanctum bearer + rotating
  refresh flow. One codebase, one auth path.
  - On Android/iOS: refresh token in the OS secure store via `@capacitor/preferences`
    (Keychain / Keystore).
  - On the PWA: the refresh token goes in an **httpOnly, Secure, SameSite=Lax cookie** scoped
    to `/api/v1/auth/refresh`, never `localStorage` — XSS reads `localStorage`, and a stolen
    refresh token is an account. The short-lived access token may live in memory only.
- **Blade public pages + Filament** → ordinary session cookies.

One token system for the app, one cookie system for the server-rendered surfaces. Not three.

### Admin panel

- 2FA **mandatory** for every Filament user. Filament ships `pragmarx/google2fa` — use it.
- IP allowlist on `/admin` if operationally feasible.
- **Every admin view of a `verification_document` writes an activity-log entry.** Not the edit —
  the *view*. Someone browsing ID cards for fun is the insider threat here, and the only way you
  ever detect it is if reads are logged.
- Signed, short-TTL (60s) URLs for document access. Never a public bucket. Never a permanent URL.

### OTP abuse

SMS pumping: an attacker triggers thousands of OTPs to premium-rate numbers and takes a cut.
Every send costs you real money.

- Rate limit by phone (3/hour), by IP (10/hour), by device (5/hour) — all three.
- Block or manually review unusual country prefixes.
- Exponential backoff per phone; hard-lock after N failed verifies.
- Alert on send-volume anomalies. Cap daily spend at the provider.

### Baseline

- `Idempotency-Key` on every mutating endpoint, backed by `idempotency_keys`. Return the stored
  response on replay; never re-execute.
- Rate limits per user and per IP on all write endpoints.
- Encrypt `verification_documents` at rest — separate bucket, separate credentials from public
  media, server-side encryption with a key you control.
- Laravel Policies on every model. Default deny.
- Never interpolate into raw SQL, including PostGIS expressions. Bind.
- Webhook signature verification before parsing (doc 03).
- `app_version` header on every request → force-update kill switch. Build it before launch, not
  after you need it.
- Strip EXIF on upload; store geo in the DB column.
- Secrets in the environment, never in the repo. Rotate on staff change.
- Dependabot + `composer audit` in CI. Filament and Laravel both ship security advisories.

### PII minimisation

| Data | Before engagement | After engagement |
|---|---|---|
| Exact address | ❌ quarter + approximate point only | ✅ full, plus landmark note |
| Phone number | ❌ | ✅ (logged reveal) |
| Full name | First name + initial | ✅ |
| Photo | ✅ (needed for recognition) | ✅ |

Implement as an API Resource concern, not per-controller. `JobResource::forViewer($party)`.
One place to get it right, one place to test.

---

## 4. Legal — Cameroon Law No. 2024/017

**This is live now.** Law No. 2024/017 on the protection of personal data was adopted
23 December 2024 with an 18-month transition period. **That deadline expired 23 June 2026** —
before you start building. Processing outside its conditions is sanctionable now, not
eventually. The supervisory authority is the CNDP (Commission Nationale de Protection des
Données à Caractère Personnel).

Points that hit this architecture directly:

0. **Consent must be captured in the language it was shown in.** `consents.presented_locale`
   (doc 09). A French consent shown to an English-operating user is not "informed." Terms,
   privacy, and every prompt exist and are versioned in both languages.

1. **Consent is elevated as the lawful basis.** Like most francophone African regimes and
   unlike the GDPR, "legitimate interest" is *not* available as an exception to the consent
   requirement. You cannot justify location tracking or ID retention on "we need it to run the
   service." You need explicit, informed, specific, voluntary consent — captured, versioned,
   and revocable. Hence the `consents` table with `policy_version`. Granular keys:
   `terms`, `privacy`, `location_tracking`, `id_verification`, `marketing` — separately
   grantable and separately revocable.

2. **Prior authorisation from the Authority may be required before processing.** Document your
   processing activities now, as a register, because you'll need them for the application. This
   is a founder task, not a code task — but it gates launch, so put it on the plan.

3. **Cross-border transfer requires approval.** This makes your hosting region an
   *architectural and legal* decision. Defaulting to `eu-west-1` because it's familiar is a
   cross-border transfer of Cameroonian personal data. Options: host in-country/regionally, or
   obtain authorisation for the transfer. **Resolve this before you pick a region** — migrating
   a live PostGIS database and an encrypted document bucket across regions later is genuinely
   painful.

4. **Extraterritorial scope** — the Act reaches data subjects located in Cameroon, including
   people merely in transit. Being incorporated elsewhere does not exempt you.

5. **Data subject rights** — access, rectification, erasure. Build the DSAR export and the
   deletion path **from day one**. Retrofitting "delete this human" across a ledger is a
   nightmare, which brings us to:

### The erasure vs. ledger conflict — solve it now

A data subject can request erasure. Your ledger is append-only and must never be deleted. These
are in direct conflict, and it will surprise you at the worst time.

**Resolution: crypto-shredding, plus a personal/financial split.**

- Ledger entries reference `party_id` and amounts only. **No PII in the ledger. Ever.** No
  names, no phone numbers, not even in the `memo` field. Enforce this in review.
- PII lives in `users`/`parties`/`addresses`/`verification_documents`, encrypted with a
  **per-party data key**.
- Erasure = destroy the party's data key + null the plaintext identifiers + tombstone the party.
  The ledger keeps referring to a `party_id` that now resolves to nothing legible. Financial
  history stays intact and auditable; the human becomes unidentifiable.
- Keep a documented retention schedule: verification documents purged N days after account
  closure or rejection; work-session geo aggregated after 90 days; messages retained per policy.

Write this design down in your processing register. It's the answer to "how do you reconcile
erasure with financial record-keeping," and the regulator will ask.

### Also in force

Law No. 2010/012 on Cybersecurity and Cybercrime still applies alongside the new Act. Get a
Cameroonian lawyer to review before launch — **this document is not legal advice**, and the
compliance deadline has already passed, so treat it as urgent rather than background.
