# 02 — Domain model

> ⚠️ **Partially superseded by `docs/06-engagement-workspace.md`.** Doc 06 owns:
> `engagement_mode` (work may be remote — `jobs.address_id` is **nullable**), versioned
> `quotations`, `milestones`, `deliverables`, `site_visits`, `warranties`, the extended
> `messages` schema, and the full lifecycle. This doc remains authoritative for identity,
> catalog, geo, assignments, reviews, referrals, safety and platform plumbing.
> **Read 06 before touching jobs, offers or engagements.**

PostgreSQL 16+ with PostGIS. All PKs are `uuid` (use `uuidv7()` if available, else app-generated
UUIDv7 — time-ordered UUIDs matter for index locality; do **not** use random UUIDv4 as a PK).

Enable up front: `CREATE EXTENSION postgis; CREATE EXTENSION citext; CREATE EXTENSION pg_trgm;`

## Enum types

```sql
CREATE TYPE party_kind        AS ENUM ('individual','organization');
CREATE TYPE user_status       AS ENUM ('pending','active','suspended','closed');
CREATE TYPE membership_role   AS ENUM ('owner','admin','dispatcher','finance','worker');
CREATE TYPE price_model       AS ENUM ('hourly','fixed','quote_only');
CREATE TYPE assignment_mode   AS ENUM ('direct','dispatch','bidding');
CREATE TYPE job_status        AS ENUM (
  'draft','open','offered','engaged','scheduled','en_route','in_progress',
  'work_submitted','completed','cancelled','disputed','closed');
CREATE TYPE offer_origin      AS ENUM ('customer_direct','system_dispatch','provider_bid');
CREATE TYPE offer_status      AS ENUM ('pending','accepted','declined','withdrawn','expired','superseded');
CREATE TYPE assignment_role   AS ENUM ('lead','helper');
CREATE TYPE assignment_status AS ENUM ('assigned','accepted','declined','en_route','on_site','completed','removed');
CREATE TYPE doc_kind          AS ENUM ('national_id_front','national_id_back','passport','selfie',
                                       'trade_license','insurance_cert','org_registration','address_proof');
CREATE TYPE doc_status        AS ENUM ('pending','approved','rejected','expired');
CREATE TYPE account_kind      AS ENUM ('platform_cash','platform_revenue','escrow_liability',
                                       'provider_payable','lead_credit_liability','promo_liability',
                                       'gateway_receivable','write_off');
CREATE TYPE entry_direction   AS ENUM ('debit','credit');
CREATE TYPE txn_kind          AS ENUM ('collection','escrow_capture','escrow_release','escrow_refund',
                                       'lead_credit_purchase','lead_credit_spend','payout','fee',
                                       'promo_grant','promo_spend','adjustment');
CREATE TYPE payment_status    AS ENUM ('pending','processing','succeeded','failed','expired','reversed');
CREATE TYPE review_visibility AS ENUM ('pending','published','withheld');
CREATE TYPE referral_status   AS ENUM ('pending','qualified','rewarded','rejected');
CREATE TYPE safety_alert_kind AS ENUM ('panic','no_show','unsafe_site','harassment','check_in_overdue');
```

---

## Identity

```sql
parties (
  id uuid PK,
  kind party_kind NOT NULL,
  display_name text NOT NULL,
  status user_status NOT NULL DEFAULT 'pending',
  created_at, updated_at
);

users (
  id uuid PK,
  party_id uuid NOT NULL UNIQUE REFERENCES parties(id),
  phone_e164 citext NOT NULL UNIQUE,          -- primary identifier in this market
  email citext UNIQUE,                        -- nullable: many users have no email
  password_hash text,                         -- nullable: OTP-only accounts are valid
  locale text NOT NULL DEFAULT 'fr',          -- fr | en. Cameroon is bilingual. Do not hardcode.
  phone_verified_at timestamptz,
  email_verified_at timestamptz,
  last_login_at timestamptz,
  status user_status NOT NULL DEFAULT 'pending',
  created_at, updated_at,
  CONSTRAINT users_party_is_individual CHECK (true)  -- enforced by trigger, see below
);

organizations (
  id uuid PK,
  party_id uuid NOT NULL UNIQUE REFERENCES parties(id),
  legal_name text NOT NULL,
  rccm_number text,        -- Registre du Commerce
  niu text,                -- Numéro d'Identifiant Unique (tax)
  created_at, updated_at
);

memberships (
  id uuid PK,
  user_id uuid NOT NULL REFERENCES users(id),
  organization_id uuid NOT NULL REFERENCES organizations(id),
  role membership_role NOT NULL,
  status text NOT NULL DEFAULT 'active',
  invited_by_user_id uuid REFERENCES users(id),
  accepted_at timestamptz,
  created_at, updated_at,
  UNIQUE (user_id, organization_id)
);
```

Add a trigger enforcing `users.party_id → parties.kind = 'individual'` and
`organizations.party_id → parties.kind = 'organization'`. A CHECK can't cross tables; use a
constraint trigger.

> **Why phone is the primary identifier:** email penetration is low, phone penetration exceeds
> 85%. Email-first signup will cost you conversions. Design OTP-first, password optional.

## Auth

```sql
refresh_tokens (
  id uuid PK,
  user_id uuid NOT NULL REFERENCES users(id),
  family_id uuid NOT NULL,               -- rotation family, for reuse detection
  token_hash text NOT NULL UNIQUE,       -- store hash, never the token
  device_id uuid REFERENCES devices(id),
  expires_at timestamptz NOT NULL,
  revoked_at timestamptz,
  replaced_by_id uuid REFERENCES refresh_tokens(id),
  created_at
);

devices (
  id uuid PK,
  user_id uuid NOT NULL REFERENCES users(id),
  platform text NOT NULL,                -- android | ios
  push_token text,
  app_version text NOT NULL,             -- drives the force-update kill switch
  last_seen_at timestamptz,
  revoked_at timestamptz,
  created_at,
  UNIQUE (user_id, push_token)
);

otp_challenges (
  id uuid PK,
  phone_e164 citext NOT NULL,
  code_hash text NOT NULL,
  purpose text NOT NULL,                 -- signup | login | phone_change
  attempts smallint NOT NULL DEFAULT 0,
  consumed_at timestamptz,
  expires_at timestamptz NOT NULL,
  created_at
);
CREATE INDEX ON otp_challenges (phone_e164, created_at DESC);
```

> **SMS pumping is a real fraud vector.** Every OTP send costs you money and an attacker can
> mint thousands. Rate limit hard by phone, by IP, by device, and by prefix. See doc 04.

## Geo and catalog

```sql
addresses (
  id uuid PK,
  party_id uuid NOT NULL REFERENCES parties(id),
  label text,
  line1 text NOT NULL,
  quarter text,             -- neighbourhood — often more meaningful than a street here
  city text NOT NULL,
  region text,
  country_code char(2) NOT NULL DEFAULT 'CM',
  point geography(Point,4326) NOT NULL,
  landmark_note text,       -- "behind the Total station" — realistic addressing
  created_at, updated_at
);
CREATE INDEX ON addresses USING GIST (point);

skills (
  id uuid PK,
  parent_id uuid REFERENCES skills(id),
  slug citext NOT NULL UNIQUE,
  name_fr text NOT NULL,
  name_en text NOT NULL,
  is_leaf boolean NOT NULL DEFAULT true,
  requires_license boolean NOT NULL DEFAULT false,
  risk_tier smallint NOT NULL DEFAULT 1,   -- 1..3; drives required verification tier
  created_at, updated_at
);

provider_profiles (
  id uuid PK,
  party_id uuid NOT NULL UNIQUE REFERENCES parties(id),
  headline text,
  bio text,
  verification_tier smallint NOT NULL DEFAULT 0,   -- see doc 04
  rating_avg numeric(3,2),        -- cached, derived; recomputed by job, never authoritative
  rating_count integer NOT NULL DEFAULT 0,
  jobs_completed integer NOT NULL DEFAULT 0,
  accepts_direct boolean NOT NULL DEFAULT true,
  accepts_dispatch boolean NOT NULL DEFAULT false,
  accepts_bidding boolean NOT NULL DEFAULT false,
  suspended_at timestamptz,
  created_at, updated_at
);

service_areas (
  id uuid PK,
  provider_profile_id uuid NOT NULL REFERENCES provider_profiles(id),
  center geography(Point,4326) NOT NULL,
  radius_m integer NOT NULL CHECK (radius_m BETWEEN 500 AND 100000),
  created_at
);
CREATE INDEX ON service_areas USING GIST (center);

provider_skills (
  id uuid PK,
  provider_profile_id uuid NOT NULL REFERENCES provider_profiles(id),
  skill_id uuid NOT NULL REFERENCES skills(id),
  price_model price_model NOT NULL,
  rate_minor bigint CHECK (rate_minor >= 0),
  currency char(3) NOT NULL DEFAULT 'XAF',
  years_experience smallint,
  UNIQUE (provider_profile_id, skill_id)
);
```

> **PostGIS from day one.** Dispatch ranking *is* a query over `ST_DWithin` + skill match +
> rating + verification tier. Retrofitting radius search onto lat/lng floats is miserable.
> Enable the extension even while v1 only does a naive distance sort.

## Jobs, offers, engagements, assignments

This is the core. Read `docs/01` §1 first if the four-table split isn't obvious.

> **Table name: `service_jobs`.** Laravel reserves `jobs` for its queue table, so the domain jobs
> table is named `service_jobs` (the model is still `Job`, and everything references it as a "job").
> Geography is conditional per doc 06: `address_id` is **nullable** with a DB CHECK
> `(engagement_mode = 'remote' OR address_id IS NOT NULL)`, and there is an `engagement_mode`
> column — both superseding the `address_id NOT NULL` shown below.

```sql
service_jobs (   -- named to avoid colliding with Laravel's queue `jobs` table
  id uuid PK,
  reference citext NOT NULL UNIQUE,          -- human-quotable, e.g. "JOB-7K2M9"
  customer_party_id uuid NOT NULL REFERENCES parties(id),
  created_by_user_id uuid NOT NULL REFERENCES users(id),
  skill_id uuid NOT NULL REFERENCES skills(id),
  address_id uuid NOT NULL REFERENCES addresses(id),
  title text NOT NULL,
  description text,
  mode assignment_mode NOT NULL,
  status job_status NOT NULL DEFAULT 'draft',
  price_model price_model NOT NULL,
  budget_minor bigint CHECK (budget_minor >= 0),
  currency char(3) NOT NULL DEFAULT 'XAF',
  scheduled_window tstzrange,
  urgency smallint NOT NULL DEFAULT 1,
  requires_verified_provider boolean NOT NULL DEFAULT false,
  published_at timestamptz,
  cancelled_at timestamptz,
  cancel_reason text,
  created_at, updated_at
);
CREATE INDEX ON jobs (status, published_at DESC);
CREATE INDEX ON jobs (customer_party_id, created_at DESC);

job_offers (
  id uuid PK,
  job_id uuid NOT NULL REFERENCES jobs(id),
  provider_party_id uuid NOT NULL REFERENCES parties(id),
  origin offer_origin NOT NULL,
  status offer_status NOT NULL DEFAULT 'pending',
  amount_minor bigint CHECK (amount_minor >= 0),
  currency char(3) NOT NULL DEFAULT 'XAF',
  message text,
  expires_at timestamptz NOT NULL,
  responded_at timestamptz,
  created_at, updated_at,
  UNIQUE (job_id, provider_party_id)     -- one live offer per pro per job
);
CREATE INDEX ON job_offers (provider_party_id, status, created_at DESC);

-- Exactly one accepted offer per job, enforced by the DB, not by hope:
CREATE UNIQUE INDEX one_accepted_offer_per_job
  ON job_offers (job_id) WHERE status = 'accepted';

engagements (
  id uuid PK,
  job_id uuid NOT NULL UNIQUE REFERENCES jobs(id),   -- one engagement per job
  provider_party_id uuid NOT NULL REFERENCES parties(id),
  offer_id uuid NOT NULL REFERENCES job_offers(id),
  agreed_amount_minor bigint NOT NULL CHECK (agreed_amount_minor >= 0),
  currency char(3) NOT NULL DEFAULT 'XAF',
  platform_fee_minor bigint NOT NULL DEFAULT 0,
  is_escrowed boolean NOT NULL DEFAULT false,
  accepted_at timestamptz NOT NULL,
  completed_at timestamptz,
  created_at, updated_at
);

assignments (
  id uuid PK,
  engagement_id uuid NOT NULL REFERENCES engagements(id),
  worker_user_id uuid NOT NULL REFERENCES users(id),
  role assignment_role NOT NULL DEFAULT 'lead',
  status assignment_status NOT NULL DEFAULT 'assigned',
  assigned_by_user_id uuid NOT NULL REFERENCES users(id),
  assigned_at timestamptz NOT NULL DEFAULT now(),
  created_at, updated_at,
  UNIQUE (engagement_id, worker_user_id)
);
CREATE UNIQUE INDEX one_lead_per_engagement
  ON assignments (engagement_id) WHERE role = 'lead' AND status <> 'removed';
```

**The uniform-assignment rule.** When an engagement is created and the provider party is an
individual, the system **auto-creates one `assignments` row** with `worker_user_id` = that
individual's user and `role='lead'`. When the provider party is an organization, a dispatcher
creates them. The app's provider section queries `assignments` only. It never branches on
individual-vs-company. Enforce the auto-create inside `AcceptOfferAction`, not a model event.

**Authorisation rule.** A dispatcher may only assign `worker_user_id`s that hold an active
`membership` in the same organization as `engagement.provider_party_id`. This is a Policy check
*and* a DB constraint trigger. It is the boundary that keeps one company from assigning
another company's staff.

## Work execution and reporting

```sql
work_sessions (
  id uuid PK,
  assignment_id uuid NOT NULL REFERENCES assignments(id),
  started_at timestamptz NOT NULL,
  start_point geography(Point,4326),
  start_accuracy_m real,
  ended_at timestamptz,
  end_point geography(Point,4326),
  end_accuracy_m real,
  created_at,
  CHECK (ended_at IS NULL OR ended_at >= started_at)
);

job_reports (
  id uuid PK,
  assignment_id uuid NOT NULL REFERENCES assignments(id),
  summary text NOT NULL,
  materials jsonb NOT NULL DEFAULT '[]',   -- [{label, qty, unit_cost_minor}]
  extra_charges_minor bigint NOT NULL DEFAULT 0,
  customer_signature_path text,
  submitted_at timestamptz,
  created_at, updated_at
);

media (
  id uuid PK,
  owner_party_id uuid NOT NULL REFERENCES parties(id),
  attachable_type text NOT NULL,    -- 'job' | 'job_report' | 'verification_document' | 'message'
  attachable_id uuid NOT NULL,
  kind text NOT NULL,               -- 'before' | 'after' | 'issue' | 'id_doc' | 'attachment'
  storage_path text NOT NULL,
  sha256 char(64) NOT NULL,
  bytes bigint NOT NULL,
  captured_point geography(Point,4326),   -- server-recorded at upload, EXIF stripped from file
  captured_at timestamptz,
  created_at
);
```

> **Strip EXIF from stored files, record geo in the DB column instead.** Serving a customer's
> photo with embedded GPS to a provider is a privacy leak. Record the metadata server-side;
> deliver a clean file.

## Messaging

```sql
conversations (
  id uuid PK,
  job_id uuid UNIQUE REFERENCES jobs(id),
  created_at
);

conversation_participants (
  conversation_id uuid NOT NULL REFERENCES conversations(id),
  party_id uuid NOT NULL REFERENCES parties(id),
  user_id uuid NOT NULL REFERENCES users(id),
  joined_at timestamptz NOT NULL DEFAULT now(),
  last_read_at timestamptz,
  PRIMARY KEY (conversation_id, user_id)
);

messages (
  id uuid PK,
  conversation_id uuid NOT NULL REFERENCES conversations(id),
  sender_user_id uuid NOT NULL REFERENCES users(id),
  body text NOT NULL,
  contact_flag text,          -- 'phone' | 'email' | null — detected, logged, NOT blocked in v1
  created_at
);
CREATE INDEX ON messages (conversation_id, created_at DESC);
```

## Money

Full treatment in `docs/03-money-and-ledger.md`. Tables: `ledger_accounts`,
`ledger_transactions`, `ledger_entries`, `payment_intents`, `payouts`, `payment_events`.

## Reviews

```sql
reviews (
  id uuid PK,
  engagement_id uuid NOT NULL REFERENCES engagements(id),
  author_party_id uuid NOT NULL REFERENCES parties(id),
  subject_party_id uuid NOT NULL REFERENCES parties(id),
  subject_worker_user_id uuid REFERENCES users(id),   -- set when provider is an org
  rating smallint NOT NULL CHECK (rating BETWEEN 1 AND 5),
  body text,
  private_note text,                     -- never published; visible to subject only
  visibility review_visibility NOT NULL DEFAULT 'pending',
  submitted_at timestamptz NOT NULL DEFAULT now(),
  published_at timestamptz,
  window_closes_at timestamptz NOT NULL,
  created_at,
  UNIQUE (engagement_id, author_party_id)
);
```

**Double-blind / simultaneous reveal.** Both sides get a 14-day window. Nothing is visible to
anyone until *both* have submitted, or the window expires — then both publish at once. Airbnb
ran this as a large-scale experiment: it reduced retaliation and reciprocation, *increased*
total review rates (people are curious what the other side wrote), and lowered average ratings
by ~1.5pp because the ratings became more honest. eBay's old two-sided system saw over 20% of
negative buyer reviews answered by a negative seller review. Build the reveal mechanism from
the start — retrofitting it after your ratings are all 4.9 is pointless.

**Rate the worker, not just the company.** When `subject_party_id` is an organization, also
capture `subject_worker_user_id`. The company's public rating aggregates its workers; the
per-worker rating stays internal to the company and the platform. A good company with one bad
technician is a fixable problem — but only if you can see it.

**Use a shrinkage estimator for display**, not a raw mean. A new pro with one 5★ should not
outrank a veteran at 4.8. Bayesian average toward the category mean with a prior weight of
~10 reviews. Store `rating_avg` as a cache; recompute on publish.

## Referrals

```sql
referral_codes (
  id uuid PK,
  party_id uuid NOT NULL UNIQUE REFERENCES parties(id),
  code citext NOT NULL UNIQUE,
  created_at
);

referrals (
  id uuid PK,
  referrer_party_id uuid NOT NULL REFERENCES parties(id),
  referee_party_id uuid NOT NULL UNIQUE REFERENCES parties(id),  -- referred once, ever
  code citext NOT NULL,
  status referral_status NOT NULL DEFAULT 'pending',
  qualifying_engagement_id uuid REFERENCES engagements(id),
  qualified_at timestamptz,
  reward_transaction_id uuid REFERENCES ledger_transactions(id),
  created_at,
  CHECK (referrer_party_id <> referee_party_id)
);
```

**Reward on a qualifying event, never on signup.** Qualifying = referee's first engagement
reaches `completed` *and* is paid. Rewarding signup is an invitation to mint fake accounts, and
in a market where SIM cards are cheap you will be farmed within a week.

Anti-fraud: unique phone, device fingerprint on `devices`, self-referral blocked by the CHECK,
velocity limits per referrer per week, and a manual review queue above a threshold. Rewards are
ledger entries against `promo_liability` — a referral credit is a real liability on your books.

## Trust and safety

```sql
verification_documents (
  id uuid PK,
  party_id uuid NOT NULL REFERENCES parties(id),
  subject_user_id uuid REFERENCES users(id),   -- which human, when party is an org
  kind doc_kind NOT NULL,
  storage_path text NOT NULL,        -- encrypted bucket, separate from public media
  sha256 char(64) NOT NULL,
  status doc_status NOT NULL DEFAULT 'pending',
  reviewed_by_user_id uuid REFERENCES users(id),
  reviewed_at timestamptz,
  reject_reason text,
  expires_at timestamptz,
  created_at, updated_at
);

emergency_contacts (
  id uuid PK,
  user_id uuid NOT NULL REFERENCES users(id),
  name text NOT NULL,
  phone_e164 citext NOT NULL,
  created_at
);

safety_alerts (
  id uuid PK,
  user_id uuid NOT NULL REFERENCES users(id),
  assignment_id uuid REFERENCES assignments(id),
  kind safety_alert_kind NOT NULL,
  point geography(Point,4326),
  note text,
  status text NOT NULL DEFAULT 'open',
  created_at,
  resolved_at timestamptz,
  resolved_by_user_id uuid REFERENCES users(id)
);

reports (
  id uuid PK,
  reporter_party_id uuid NOT NULL REFERENCES parties(id),
  subject_party_id uuid NOT NULL REFERENCES parties(id),
  job_id uuid REFERENCES jobs(id),
  category text NOT NULL,
  body text NOT NULL,
  status text NOT NULL DEFAULT 'open',
  created_at, resolved_at
);

blocks (
  party_id uuid NOT NULL REFERENCES parties(id),
  blocked_party_id uuid NOT NULL REFERENCES parties(id),
  created_at,
  PRIMARY KEY (party_id, blocked_party_id),
  CHECK (party_id <> blocked_party_id)
);
```

A `block` must be honoured in dispatch ranking, in search results, and in offer creation —
in all three, or it isn't a block.

## Platform plumbing

```sql
consents (
  id uuid PK,
  user_id uuid NOT NULL REFERENCES users(id),
  policy_key text NOT NULL,          -- 'terms' | 'privacy' | 'location_tracking' | 'id_verification'
  policy_version text NOT NULL,
  granted boolean NOT NULL,
  granted_at timestamptz NOT NULL,
  ip inet,
  user_agent text,
  created_at
);
CREATE INDEX ON consents (user_id, policy_key, granted_at DESC);

idempotency_keys (
  id uuid PK,
  key text NOT NULL,
  user_id uuid REFERENCES users(id),
  endpoint text NOT NULL,
  request_hash char(64) NOT NULL,
  response_status smallint,
  response_body jsonb,
  created_at,
  expires_at timestamptz NOT NULL,
  UNIQUE (key, endpoint, user_id)
);

outbox_messages (
  id uuid PK,
  topic text NOT NULL,
  payload jsonb NOT NULL,
  available_at timestamptz NOT NULL DEFAULT now(),
  processed_at timestamptz,
  attempts smallint NOT NULL DEFAULT 0,
  last_error text,
  created_at
);
CREATE INDEX ON outbox_messages (processed_at, available_at) WHERE processed_at IS NULL;
```

Use `spatie/laravel-activitylog` for audit trails; add a mandatory log entry on every admin
view of a `verification_document` (see doc 04).

---

## State machines

Implement each as a class in `app/Domain/*/StateMachine`. Illegal transitions throw. No
controller assigns `status` directly.

### Job

```
draft ──publish──> open ──offer_created──> offered ──offer_accepted──> engaged
                                                                          │
                                              ┌───────schedule────────────┘
                                              ▼
                                          scheduled ──worker_departs──> en_route
                                              │                             │
                                              └────────check_in────────────>│
                                                                            ▼
                                                                       in_progress
                                                                            │ submit_report
                                                                            ▼
                                                                      work_submitted
                                                              ┌─────────────┤
                                              customer_approves│             │dispute
                                                              ▼             ▼
                                                          completed      disputed
                                                              │             │ resolve
                                                              │ settle      ▼
                                                              ▼         completed
                                                            closed
```

`cancelled` is reachable from `draft|open|offered|engaged|scheduled` only. After `en_route`,
cancellation becomes a dispute — someone has already spent fuel.

`work_submitted → completed` also fires on an **auto-approve timer** (72h default). Without it,
providers never get paid, because customers forget. This one timer is the difference between a
platform that works and one that generates support tickets forever.

### Offer

```
pending ──accept──> accepted
   ├──decline──> declined
   ├──withdraw──> withdrawn        (provider-initiated)
   ├──expire───> expired           (scheduled job, on expires_at)
   └──supersede> superseded        (another offer on the same job was accepted)
```

### The concurrency problem you must solve

Two providers accepting the same dispatched job at the same instant. This is *the* bug in this
domain. The `one_accepted_offer_per_job` partial unique index is your backstop, but do not rely
on catching a unique violation as flow control.

`AcceptOfferAction` must:

```php
DB::transaction(function () use ($offer) {
    // 1. Lock the JOB row, not the offer. The job is the contended resource.
    $job = Job::whereKey($offer->job_id)->lockForUpdate()->firstOrFail();

    // 2. Re-check state under the lock. The world changed while you waited.
    if ($job->status !== JobStatus::Offered) {
        throw new OfferNoLongerAvailable();
    }

    // 3. Accept this offer, supersede all siblings.
    $offer->update(['status' => OfferStatus::Accepted, 'responded_at' => now()]);
    JobOffer::where('job_id', $job->id)
        ->whereKeyNot($offer->id)
        ->where('status', OfferStatus::Pending)
        ->update(['status' => OfferStatus::Superseded]);

    // 4. Create engagement + auto-assign if provider is an individual.
    $engagement = CreateEngagementAction::handle($job, $offer);

    // 5. Side effects go to the OUTBOX, not dispatch(). The transaction may still roll back.
    Outbox::publish('offer.accepted', [...]);

    $job->transitionTo(JobStatus::Engaged);
});
```

`lockForUpdate()` on the job, re-check under the lock, outbox for side effects. Write a Pest
test that fires N concurrent accepts and asserts exactly one engagement exists.
