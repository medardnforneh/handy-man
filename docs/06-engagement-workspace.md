# 06 — The engagement workspace

This document supersedes the thin `offer → accept → engagement` flow in doc 02. That model was
built for "book a handyman." The vision is larger: a workspace where two parties negotiate,
agree, execute, and settle — whether or not they ever meet.

## The mode split (this changes everything upstream)

> "Engagements may be completed entirely remotely, entirely on-site, or through a hybrid
> approach."

This is not a minor flag. It means **geography is optional**. A remote engagement has no
address, no dispatch radius, no check-in, no panic button, and no travel. A remote job has
deliverables instead of photos.

```sql
CREATE TYPE engagement_mode AS ENUM ('onsite','remote','hybrid');

ALTER TABLE jobs ADD COLUMN engagement_mode engagement_mode NOT NULL;
ALTER TABLE jobs ALTER COLUMN address_id DROP NOT NULL;
ALTER TABLE jobs ADD CONSTRAINT jobs_address_required_when_physical
  CHECK (engagement_mode = 'remote' OR address_id IS NOT NULL);
```

Two distinct axes that are easy to conflate — keep them separate:

| Axis | Column | Values | Question it answers |
|---|---|---|---|
| Assignment | `jobs.mode` | direct / dispatch / bidding | *How was the provider chosen?* |
| Execution | `jobs.engagement_mode` | onsite / remote / hybrid | *Where does the work happen?* |

**Feature applicability matrix.** Encode this as a policy object, not scattered `if` statements:

| Feature | onsite | remote | hybrid |
|---|---|---|---|
| Address, PostGIS dispatch, radius | ✅ | ❌ | ✅ |
| Site visit | ✅ | ❌ | ✅ |
| Check-in/out, work sessions | ✅ | ❌ | ✅ (on-site portions) |
| Panic button, share-my-job | ✅ | ❌ | ✅ |
| Before/after photos | ✅ required | ❌ | ✅ |
| Deliverables (files) | optional | ✅ required | ✅ |
| Video consultation | optional | ✅ | ✅ |
| Warranty | ✅ | ✅ | ✅ |

> Precedent: KnockNok runs both on-site and live *online* consultations against the same
> provider pool. The remote path is not hypothetical — it's how you serve diagnosis-before-visit
> and pure-advice engagements, and it's the only path that works when the provider is in Douala
> and the customer is in Yaoundé.

## The real lifecycle

```
create ─> interest ─> discussion ─> [site visit] ─> quotation ─> timeline proposal
                                                          │
                                            ┌─────────────┴──────────┐
                                        revise (v2, v3…)         agreement
                                                                     │
                                                                  deposit
                                                                     │
                                                                 execution ──> milestones
                                                                     │
                                                                completion
                                                                     │
                                                          customer approval
                                                                     │
                                                              final payment
                                                                     │
                                                                  review
                                                                     │
                                                                 warranty
```

Two things this adds that the old model could not express:

1. **Negotiation is a phase, not an event.** An offer is not a quote. A quote gets revised.
2. **Payment is not one moment.** Deposit → milestones → final. The ledger already supports
   this (doc 03); the domain must now model it.

## Quotations — versioned and immutable

The single most common mistake here is mutating a quote in place. Never. A submitted quote is
a legal-ish artefact the other party made a decision against.

```sql
CREATE TYPE quote_status AS ENUM ('draft','submitted','accepted','rejected','expired','withdrawn','superseded');

quotations (
  id uuid PK,
  job_id uuid NOT NULL REFERENCES jobs(id),
  provider_party_id uuid NOT NULL REFERENCES parties(id),
  version integer NOT NULL,
  supersedes_id uuid REFERENCES quotations(id),
  status quote_status NOT NULL DEFAULT 'draft',
  currency char(3) NOT NULL DEFAULT 'XAF',
  subtotal_minor bigint NOT NULL CHECK (subtotal_minor >= 0),
  deposit_minor bigint NOT NULL DEFAULT 0 CHECK (deposit_minor >= 0),
  notes text,
  -- The three dates. See below.
  customer_requested_by timestamptz,
  provider_estimated_at timestamptz,
  provider_committed_at timestamptz,
  valid_until timestamptz NOT NULL,
  submitted_at timestamptz,
  responded_at timestamptz,
  created_at, updated_at,
  UNIQUE (job_id, provider_party_id, version),
  CHECK (deposit_minor <= subtotal_minor)
);

quotation_lines (
  id uuid PK,
  quotation_id uuid NOT NULL REFERENCES quotations(id),
  position smallint NOT NULL,
  kind text NOT NULL,                     -- labour | material | travel | other
  label text NOT NULL,
  quantity numeric(12,3) NOT NULL CHECK (quantity > 0),
  unit_price_minor bigint NOT NULL CHECK (unit_price_minor >= 0),
  UNIQUE (quotation_id, position)
);

-- Only one live quote per provider per job:
CREATE UNIQUE INDEX one_live_quote_per_provider_per_job
  ON quotations (job_id, provider_party_id)
  WHERE status IN ('draft','submitted');
```

Revision = insert v(n+1) with `supersedes_id`, mark v(n) `superseded`. Never `UPDATE` a
submitted quote. The version chain is the negotiation history, and it's what you show in the
workspace timeline.

`quotations` replaces `job_offers.amount_minor` for quote-based flows. Keep `job_offers` for
the lightweight "I'm interested" signal — offer expresses *interest*, quotation expresses
*price*. In direct-booking of a fixed-price service, an offer may carry the price and no
quotation exists. Both paths converge on `engagements`.

## The three dates

> "Separate customer requested deadline from provider estimated completion and provider
> committed completion."

This is the sharpest idea in the vision doc and most platforms get it wrong by collapsing them
into one `due_date`. They are three different claims by two different parties:

| Date | Owner | Meaning | Used for |
|---|---|---|---|
| `customer_requested_by` | Customer | "I need it by Friday" | Matching, urgency ranking |
| `provider_estimated_at` | Provider | "Realistically, Tuesday" | Expectation setting |
| `provider_committed_at` | Provider | "I will be liable for Wednesday" | **SLA, on-time-rate metric** |

Only `provider_committed_at` counts toward `on_time_rate`. A provider who estimates optimistically
but commits honestly should not be punished. Conflating these produces a reputation metric that
punishes candour, and providers will learn to game it by padding every estimate.

## Milestones and deliverables

```sql
CREATE TYPE milestone_status AS ENUM ('pending','in_progress','submitted','approved','rejected','paid');

milestones (
  id uuid PK,
  engagement_id uuid NOT NULL REFERENCES engagements(id),
  position smallint NOT NULL,
  title text NOT NULL,
  amount_minor bigint NOT NULL DEFAULT 0 CHECK (amount_minor >= 0),
  due_at timestamptz,
  status milestone_status NOT NULL DEFAULT 'pending',
  submitted_at timestamptz,
  approved_at timestamptz,
  reject_reason text,
  created_at, updated_at,
  UNIQUE (engagement_id, position)
);

CREATE TYPE deliverable_status AS ENUM ('pending','submitted','accepted','rejected');

deliverables (
  id uuid PK,
  engagement_id uuid NOT NULL REFERENCES engagements(id),
  milestone_id uuid REFERENCES milestones(id),
  title text NOT NULL,
  media_id uuid REFERENCES media(id),
  status deliverable_status NOT NULL DEFAULT 'pending',
  submitted_at timestamptz,
  reviewed_at timestamptz,
  reject_reason text,
  created_at
);
```

**Invariant:** `SUM(milestones.amount_minor) = engagements.agreed_amount_minor`. Enforce with a
deferred constraint trigger. A milestone plan that doesn't add up to the agreement is a money
bug waiting to happen.

Each milestone approval releases its slice from escrow (doc 03, escrow release flow) rather
than releasing everything at completion. Deposit is milestone position 0.

## Site visits

```sql
site_visits (
  id uuid PK,
  job_id uuid NOT NULL REFERENCES jobs(id),
  provider_party_id uuid NOT NULL REFERENCES parties(id),
  scheduled_for timestamptz NOT NULL,
  is_chargeable boolean NOT NULL DEFAULT false,
  fee_minor bigint NOT NULL DEFAULT 0,
  status text NOT NULL DEFAULT 'scheduled',
  completed_at timestamptz,
  outcome_notes text,
  resulting_quotation_id uuid REFERENCES quotations(id),
  created_at
);
```

A chargeable site visit is its own tiny engagement, ledger-wise. Many providers won't quote
without seeing the job; making the visit chargeable-but-creditable (fee deducted from the
final quote if accepted) is the standard mechanic, and it filters tyre-kickers.

Site visits are `onsite`/`hybrid` only. For `remote`, the equivalent is a video consultation —
model it as a `site_visit` row with `engagement_mode='remote'` on the parent job, or a
dedicated `consultations` table if it grows features. Start with the former.

## Warranty

```sql
CREATE TYPE warranty_status AS ENUM ('active','claimed','expired','void');

warranties (
  id uuid PK,
  engagement_id uuid NOT NULL UNIQUE REFERENCES engagements(id),
  duration_days integer NOT NULL CHECK (duration_days > 0),
  starts_at timestamptz NOT NULL,
  expires_at timestamptz NOT NULL,
  terms text,
  status warranty_status NOT NULL DEFAULT 'active',
  created_at,
  CHECK (expires_at > starts_at)
);

warranty_claims (
  id uuid PK,
  warranty_id uuid NOT NULL REFERENCES warranties(id),
  claimed_by_party_id uuid NOT NULL REFERENCES parties(id),
  description text NOT NULL,
  remedy_job_id uuid REFERENCES jobs(id),
  status text NOT NULL DEFAULT 'open',
  created_at,
  resolved_at timestamptz
);
```

**A warranty claim spawns a remedy job**, linked back via `remedy_job_id`. That closes the loop:
the fix is a real job with a real assignment and a real report, not an email thread. It's also
free for the customer and unpaid for the provider — which is exactly why it must be tracked as
a first-class object rather than handled informally.

Warranty is the strongest anti-leakage mechanic you have. It only exists on-platform. A pro who
takes the job off-platform cannot offer it, and a customer who goes off-platform loses it.
Market it that way.

## The chat *is* the state machine

> "…structured actions such as submit quote, approve quote, mark on the way, arrived, started,
> paused and completed."

This is the best idea in the vision doc and it should drive the whole workspace UI. Do **not**
build a chat pane and, separately, a row of action buttons somewhere else. Every state
transition emits a structured message into the conversation. The thread becomes the timeline,
the audit log, and the UI, simultaneously.

This also matches how this market already works: people coordinate on WhatsApp. A chat-first
workspace with structured actions is a familiar surface with the mess removed.

```sql
CREATE TYPE message_kind AS ENUM (
  'text','voice','media','document','system',
  'quote_submitted','quote_revised','quote_accepted','quote_rejected',
  'milestone_submitted','milestone_approved','milestone_rejected',
  'site_visit_proposed','site_visit_confirmed',
  'on_the_way','arrived','started','paused','resumed','completed',
  'payment_requested','payment_received','deliverable_submitted');

ALTER TABLE messages ADD COLUMN kind message_kind NOT NULL DEFAULT 'text';
ALTER TABLE messages ADD COLUMN payload jsonb;          -- {quotation_id: "...", version: 2}
ALTER TABLE messages ADD COLUMN reply_to_id uuid REFERENCES messages(id);
ALTER TABLE messages ADD COLUMN voice_media_id uuid REFERENCES media(id);
ALTER TABLE messages ADD COLUMN edited_at timestamptz;
ALTER TABLE messages ADD COLUMN deleted_at timestamptz;

message_reactions (
  message_id uuid NOT NULL REFERENCES messages(id),
  user_id uuid NOT NULL REFERENCES users(id),
  emoji text NOT NULL,
  created_at,
  PRIMARY KEY (message_id, user_id, emoji)
);

message_receipts (
  message_id uuid NOT NULL REFERENCES messages(id),
  user_id uuid NOT NULL REFERENCES users(id),
  delivered_at timestamptz,
  read_at timestamptz,
  PRIMARY KEY (message_id, user_id)
);
```

**Rule: the structured message is emitted by the Action that performs the transition, inside
the same transaction, via the outbox.** Never let the client post a `quote_accepted` message —
the client requests the transition, the server performs it and narrates it. Otherwise the chat
and the state diverge and you can never trust either.

**Voice notes are not optional here.** Literacy and typing speed vary; voice is how a large
part of this market actually communicates. Record Opus, keep it short, transcribe later if ever.

## Realtime

Laravel Reverb + WebSockets, already chosen. Notes:

- **Presence, typing, and receipts are ephemeral.** Broadcast them; do not persist typing
  indicators. `message_receipts` persists, typing does not.
- **Reverb is not the source of truth.** The mobile app must reconcile via REST on reconnect —
  connections drop constantly on 3G. Design every workspace screen to be correct after a cold
  fetch, with the socket as an accelerant.
- **Channel per engagement**: `private-engagement.{id}`, authorised against a Policy that checks
  `conversation_participants`.
- **Do not broadcast money events to clients as truth.** Broadcast "something changed, refetch."
  A payment confirmation arriving over a WebSocket that the ledger hasn't committed yet is a
  support ticket at best.

## Trust metrics

> "completion rate, response time, on-time rate, repeat customer rate"

```sql
provider_metrics (
  provider_profile_id uuid PRIMARY KEY REFERENCES provider_profiles(id),
  completion_rate numeric(5,4),
  response_p50_seconds integer,
  on_time_rate numeric(5,4),
  repeat_customer_rate numeric(5,4),
  window_days smallint NOT NULL DEFAULT 90,
  sample_size integer NOT NULL,
  computed_at timestamptz NOT NULL
);
```

All derived, recomputed nightly, never authoritative. Notes that matter:

- **Rolling 90-day window**, not lifetime. Lifetime metrics make it impossible for a provider to
  recover from a bad month, which drives your good-but-unlucky supply to competitors.
- **Suppress display below a sample-size floor** (~5). "100% on-time (1 job)" is noise wearing a
  suit. Same shrinkage logic as ratings (doc 02).
- `on_time_rate` measures against `provider_committed_at` only. See "The three dates."
- `repeat_customer_rate` is your **best leakage proxy**. A provider with a high completion rate
  and a suspiciously low repeat rate is quite possibly taking repeat business off-platform.
  Don't accuse — investigate, and fix the incentive.

## What this means for the frontend

The workspace is a real-time, stateful, high-interaction surface: virtualized message list,
optimistic sends, presence, typing, inline quote cards, milestone approvals, file uploads,
voice recording. Precisely the UI class that server-round-trip frameworks handle badly — which
is why it is not Livewire.

It is also the surface both audiences share across web and mobile, which is why it is **one
Ionic + Capacitor codebase** rather than separate web and mobile implementations. It is the one
screen where the WebView performance ceiling actually bites, so virtualize the message list and
keep the workspace DOM lean (doc 08). See doc 08 for the full rationale and the Flutter switch
trigger.
