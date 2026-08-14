# Testing everything locally

How to run the whole product on this machine and click through every part of it: the customer app,
the provider section, the admin panel and the public website.

Written to be followed top to bottom the first time, and dipped into afterwards.

---

## 1. Start it

```bash
npm run dev          # start Postgres if needed, migrate, serve the API and the app
npm run dev:fresh    # the same, but DROPS the local database and reseeds the demo data
```

Use `dev:fresh` the first time, and whenever you want the demo data back the way it was. It is
destructive to the local database only.

When it comes up:

| What | Where | Sign-in |
|---|---|---|
| **The app** (customer + provider) | http://localhost:4200 | Phone number → 6-digit code |
| **Public website** | http://localhost:8100 | None — it is public on purpose |
| **Admin panel** | http://localhost:8100/admin | Email + password + 2FA |

Those ports are not arbitrary: the app's development build points at the API on `:8100`, and `:4200`
is the port the browser tooling here is allowed to drive. Serving on other ports will appear to work
and then quietly fail in ways that look like app bugs.

Ctrl-C stops both servers. Postgres is left running, because other things on this machine use it.

---

## 2. Signing in

### The app — no SMS, no log file

Type any seeded number (just the 9 digits — the `+237` is fixed) and tap **Send code**. The next
screen shows the code itself in an orange **DEV** chip; tap it to fill the boxes, then **Verify**.

That chip only exists because the API is running in its `local` environment, where nothing can send
an SMS. Any deployed environment omits the code from the response entirely, so the chip cannot
appear — it is not a setting anyone can turn on by accident.

**Any number works.** An unrecognised one signs up a brand-new customer on the spot, which is the
right way to test what a first-time user sees. The seeded people are for testing what a populated
account looks like:

| Sign in as | Number to type | What they have |
|---|---|---|
| Jean Mbarga | `620000000` | Jobs in flight, money in escrow, chat threads |
| Aïcha Bello | `620000001` | Jobs, reviews written and pending |
| Paul Etoundi | `620000002` | A completed job with a warranty |
| Grace Ngo | `620000003` | |
| Samuel Tchoua | `620000004` | |
| Fatou Sow | `620000005` | |
| Restaurant Le Palmier | `620000006` | |
| Boutique Kribi | `620000007` | |
| **Atelier Nkeng** (provider) | `610000000` | A full provider: skills, service area, active work, earnings |
| Douala Cool Services | `610000001` | Provider |
| Marie Fotso | `610000002` | Provider — remote work, deliverables |
| BTP Cameroun SARL | `610000003` | Provider |
| Éric Kamga | `610000004` | Provider |
| Fresh Design Studio | `610000005` | Provider — remote |
| Yaoundé Élec | `610000006` | Provider |
| Bâti-Pro | `610000007` | Provider |

Everyone in this product has both sides — there is no "provider account". Signing in as a provider
gives you a customer app that also has a populated provider section; signing in as a customer gives
you the same app with an empty one.

### The admin panel — mandatory 2FA, on purpose

1. http://localhost:8100/admin
2. `admin@handyman.cm` / `password`
3. You will land on a **two-factor setup** screen and cannot skip it. Scan the QR code with any
   authenticator app (Google Authenticator, 1Password, Authy…), enter the 6-digit code, and **save
   the recovery codes it shows you** — locally you can always reseed, but you will otherwise be
   locked out of the panel until you do.

This is deliberate and is not relaxed for local use: staff accounts reach real people's money,
identity documents and safety alerts, so the panel refuses to open for an un-enrolled account. If
you get locked out: `npm run dev:fresh` reseeds the admin.

### The public website

No sign-in. It is the crawlable, pre-signup face of the product: the landing page, the full trades
directory at `/services`, and a page per trade.

---

## 3. What to click, by feature

Each row says where the feature lives and what to do. Anything marked **admin only** has no screen
in the app yet — see §4.

### Finding and requesting work (customer)

| Feature | Where | Try this |
|---|---|---|
| Browse trades | App → **Discover** | Search "plom" — it matches trades, not providers, in both languages |
| Filter by category | Discover → category rail, or **All** for the full grid | The rail scrolls; picking one filters the providers below |
| On-site vs remote | Discover → the segment above the categories | Remote drops geography entirely — different providers |
| Provider profile | Discover → tap any provider | Rating, completed jobs, on-time rate, reviews. A new provider shows "Building", never a fabricated score |
| **Post a request** | App → **Jobs** → the **+** (or the button in the empty state, if you have no jobs) | Title, category, trade, on-site/remote, address, budget |
| **Save an address** | During the request, or Account → **Add an address** | Needs a location fix — the browser will ask. Without a real point the address can never be matched to a provider, so it will not save |
| Job list and detail | App → **Jobs** | Status, money, milestone progress |
| **Read and accept a quote** | Jobs → an open job with quotes (sign in as `620000001`) | Two quotes at different prices, one with a deposit and one without. Accepting confirms the figures, forms the engagement, funds escrow and generates the milestone plan |
| Matched providers | A job → **See providers** | Skill + coverage matched (on-site), whole pool (remote) |

### Doing the work (provider)

| Feature | Where | Try this |
|---|---|---|
| **Become a provider** | Account → **Offer services** (as a customer with no profile) | Headline, trades, pricing, service radius. Creates a real profile, listed skills and a service area |
| Provider dashboard | App → Account → Offer services (once you have a profile) | Wallet, active work, opportunities, client book |
| Opportunities | Provider → **Leads** | Open requests matching your trades and area |
| Quote a lead | Leads → a lead | Line items, deposit, validity — the server totals it, never the client |
| Active work | Provider → **Work** | |
| **Check in / out** | Work → a job → Check in | On-site only. Remote jobs have no check-in at all, by design |
| Status updates | Work → a job | On the way, started, paused, completed — each narrated into the customer's chat |
| Job report | Work → a job → Report | Summary, materials, before/after photos. EXIF is stripped server-side |
| **Send your papers in** | Provider → **Profile** → the verification card | A row per document. Pick any image or PDF — locally nothing checks that it is really an ID |
| **Watch a tier rise** | Send one, then Admin → Trust & safety → Verification documents → approve it | Reopen the app's verification screen: the row reads "Accepted" and the ladder lights up. Tier 2 is what lets a provider accept on-site paid work |
| Client book | Provider → **Profile** → My business | Customers, lifetime value, pipeline, re-engagement |
| Earnings | Provider → **Earnings** | Balance, lead credits and payout history |
| **Withdraw** | Earnings → **Withdraw** | Ask for part or all of the balance. Watch the available figure drop, the reserved figure appear, and the payout land in the history as pending. Admin → Money → Payouts is the same row from the other side |

### The engagement workspace

| Feature | Where | Try this |
|---|---|---|
| Chat | Jobs → a job → the thread | Free-form messages |
| Lifecycle events | The same thread | Quote accepted, work started, milestone released… all narrated by the server, never composed by the app |
| Live delivery | Open the same job in two browsers, as both parties | Messages arrive without a refresh |
| Typing + presence | Same two-browser setup | |
| Voice notes | The thread's mic button | |
| Milestone approval | Jobs → a job (the detail page, not the chat) → a submitted milestone | Releases that slice of escrow to the provider. Works offline too — it queues and sends when you reconnect |
| **Mark the work finished** | Jobs → a job → **Mark done** | Opens the 14-day review window for both sides and schedules the review nudges |
| **Leave a review** | The same card, once the work is marked done | Stars plus a sentence. It stays hidden until the other side writes theirs, or 14 days pass — the confirmation says so |

### Money

| Feature | Where | Try this |
|---|---|---|
| Escrow held | App job detail, and admin → Engagements | Deposit is captured automatically when an engagement forms |
| Ledger | Admin → **Money → Payment intents / Payouts** | Every movement is a balanced transaction; nothing is ever edited or deleted |
| Reconciliation | Admin → **Money → Reconciliation exceptions** | Seeded with one open exception — resolve it and see the audit trail |
| Marketplace analytics | Admin → dashboard | Liquidity, match rate, time-to-offer, leakage watch |

### Trust and safety

| Feature | Where | Try this |
|---|---|---|
| **The panic alert** | App → Account (or Provider → Profile) → **Safety** | **Hold** the red button for a second and a half — a tap does nothing on purpose. It raises a real alert: watch it appear in Admin → Safety alerts. Locally there is no SMS gateway, so the contacts are not actually texted |
| **Emergency contacts** | The same screen | Add one with its country code, remove it. These are who a panic alert reaches |
| Verification queue | Admin → **Trust & safety → Verification documents** | Approve one and watch the provider's tier rise. Every document *view* is written to the activity log |
| Reports | Admin → **Trust & safety → Reports** | Seeded open reports; resolve or dismiss one |
| Disputes | Admin → **Trust & safety → Disputes** | Adjudicate — a money-moving decision posts a balanced adjustment stamped with your name |
| Safety alerts | Admin → **Trust & safety → Safety alerts** | Seeded panic and check-in-overdue alerts; acknowledge and resolve |
| Activity log | Admin → **Identity → Activity logs** | Filter to staff document views — the insider-threat control |
| Referrals | Admin → **Trust & safety → Referrals** | One is flagged for velocity; clear it and it qualifies |
| People and organisations | Admin → **Identity → Parties** | Both sides of the marketplace for every party; erased parties are marked, not hidden |
| Editing the trade taxonomy | Admin → **Marketplace → Skills** | The one create/edit surface. `risk_tier` feeds the paid-work gate; changing it has teeth |

### The public site

| Feature | Where |
|---|---|
| Landing page | http://localhost:8100 |
| All trades | http://localhost:8100/services |
| One trade | Any trade from the directory |
| French / English | The switcher in the header — both languages are written copy, not machine translation |

---

## 4. What you cannot click yet

The API can do these; the app has no screen for them. This is the honest list — the tracker's
"Open gap" section keeps the full inventory, and `npm run check:uncalled` regenerates it.

| Feature | Status | Where you can still see it |
|---|---|---|
| Revising a quote (provider side) | No UI — a provider cannot re-price a submitted quote from the app | Admin → Engagements |
| Reviewing a deliverable | No UI | Admin → Engagements |
| Blocking and reporting a person | No UI | Admin → Reports (seeded) |
| Warranties and claims | No UI | Seeded warranty + claim, with the remedy job it spawned |
| Share-my-job link | No UI | Seeded shares; open `/s/{token}` |
| Referral code, rebooking, follow-ups | No UI | Admin → Referrals |
| Account erasure, data export | No UI | API only |

Two more things are external rather than unbuilt: **payments** run against a fake gateway locally
(no CinetPay credentials), and **push notifications** have no delivery target (no FCM project), so
notification rows are created and nothing arrives on a device.

---

## 5. When something looks broken

| What you see | What it usually is |
|---|---|
| A screen shows demo data that isn't yours | The session expired. Access tokens last 15 minutes — sign in again |
| The first sign-in attempt after starting does nothing | `ng serve` opens the port before it has finished its first compile, so the page loads against a half-built bundle. Reload once and it works. The same thing happens for a few seconds after any code change |
| Blank screen or stale content right after a change | The dev server was mid-rebuild. Hard-reload |
| A screen shows its empty state for a second or two, then fills in | Not a bug. The local API is PHP's built-in server, which answers about one request at a time at ~0.5s each, and a screen makes several. Nothing on a real server behaves this way |
| "Something went wrong" on sign-in | Postgres is not running. `npm run dev` starts it |
| Every label shows as `some.key.name` | The translations did not build — `npm run i18n:build` |
| Too many code requests | Local limits are already raised in `backend/.env`; if you still hit one, `php artisan cache:clear` from `backend/` |
| The admin panel will not open | 2FA is not enrolled, or the code is wrong. `npm run dev:fresh` resets the admin |
| A trade page says nobody offers this | The demo data was not seeded — `npm run dev:fresh` |

---

## 6. Reset

```bash
npm run dev:fresh
```

Drops the local database, re-runs every migration, and reseeds both the money story (`DemoSeeder`)
and the coverage data (`DemoCoverageSeeder`) that fills every admin queue and app screen. Takes a
couple of minutes. Nothing outside this machine is touched.
