# 07 — Client follow-ups and lifecycle messaging

## Why this is a domain, not a cron job

The instinct is to scatter `Notification::send()` calls through the codebase and call it done.
That produces, within three months: duplicate reminders, review requests sent to people who
already reviewed, warranty alerts for voided warranties, and users who mute your app forever.

Follow-ups are **scheduled, cancellable, deduplicated, budgeted, and attributable**. That's a
domain module.

The commercial case: **the money is in the follow-up, not the first contact.** An unconverted
quote is a job you already paid to acquire. A dormant customer is cheaper to wake than a new
one is to find. Every FSM product in the market ships this — Housecall Pro leads with "text
reminders for clients," Jobber's whole pitch is client management, and generic handyman
feature lists all name follow-up reminders, review-request automation, warranty tracking and
follow-up inspection scheduling. It's table stakes and it's where retention lives.

## The catalogue

| Kind | Trigger | Delay | Cancelled by |
|---|---|---|---|
| `quote_pending_customer` | Quote submitted | 48h, then 5d | Quote accepted/rejected/expired |
| `quote_expiring` | Quote submitted | 24h before `valid_until` | Any response |
| `job_unquoted` | Job open, no offers | 6h | First offer |
| `site_visit_reminder` | Visit scheduled | 24h + 2h before | Visit completed/cancelled |
| `job_starting_soon` | Engagement scheduled | 24h + 1h before | Cancellation |
| `check_in_overdue` | `en_route`, no `on_site` | 30m after ETA | Check-in |
| `awaiting_approval` | `work_submitted` | 24h | Approval or dispute |
| `auto_approve_warning` | `work_submitted` | 48h (24h before auto-approve) | Approval or dispute |
| `review_request` | Engagement `completed` | 2h | Review submitted |
| `review_reminder` | Engagement `completed` | 3d | Review submitted / window closed |
| `payment_due` | Invoice issued | On due date, then +3d | Payment |
| `payout_ready` | `provider_payable` > threshold | Immediate | Payout requested |
| `warranty_expiring` | Warranty active | 14d before `expires_at` | Claim opened / voided |
| `maintenance_due` | Engagement completed, skill has interval | skill-defined (e.g. 6mo) | New job in category |
| `reengagement` | No job in 90d | 90d, then 180d | Any new job |
| `abandoned_draft` | Job in `draft` | 24h | Publish or delete |

Two of these earn their keep more than the rest:

- **`quote_pending_customer`** — recovering unconverted quotes is the highest-ROI message on
  the list. The lead is already paid for.
- **`warranty_expiring`** — it's a service message, not marketing, so it's welcome; it reminds
  the customer the platform gave them something the off-platform alternative didn't; and it
  frequently converts into a `maintenance_due` job.

## Schema

```sql
CREATE TYPE followup_kind AS ENUM (
  'quote_pending_customer','quote_expiring','job_unquoted','site_visit_reminder',
  'job_starting_soon','check_in_overdue','awaiting_approval','auto_approve_warning',
  'review_request','review_reminder','payment_due','payout_ready',
  'warranty_expiring','maintenance_due','reengagement','abandoned_draft');

CREATE TYPE followup_channel AS ENUM ('in_app','push','sms','whatsapp','email');
CREATE TYPE followup_status  AS ENUM ('scheduled','sent','cancelled','responded','failed','suppressed');

follow_ups (
  id uuid PK,
  kind followup_kind NOT NULL,
  target_party_id uuid NOT NULL REFERENCES parties(id),
  target_user_id uuid NOT NULL REFERENCES users(id),
  job_id uuid REFERENCES jobs(id),
  engagement_id uuid REFERENCES engagements(id),
  quotation_id uuid REFERENCES quotations(id),
  warranty_id uuid REFERENCES warranties(id),
  channel followup_channel NOT NULL,
  scheduled_for timestamptz NOT NULL,
  status followup_status NOT NULL DEFAULT 'scheduled',
  dedupe_key text NOT NULL UNIQUE,
  attempts smallint NOT NULL DEFAULT 0,
  sent_at timestamptz,
  cancelled_at timestamptz,
  cancel_reason text,
  responded_at timestamptz,
  response_action text,
  failure_reason text,
  created_at, updated_at
);
CREATE INDEX ON follow_ups (status, scheduled_for) WHERE status = 'scheduled';

comms_log (
  id uuid PK,
  user_id uuid NOT NULL REFERENCES users(id),
  channel followup_channel NOT NULL,
  purpose text NOT NULL,
  follow_up_id uuid REFERENCES follow_ups(id),
  sent_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON comms_log (user_id, channel, sent_at DESC);
```

### `dedupe_key` is the whole design

Format: `{kind}:{anchor_type}:{anchor_id}:{sequence}` — e.g.
`review_request:engagement:0193f2a1-…:1`, `quote_pending_customer:quotation:0193…:2`.

`UNIQUE` on it means **scheduling is idempotent**. Replay the same event ten times, get one
follow-up. This matters because your event source is an at-least-once outbox — duplicates are
guaranteed, not hypothetical.

## The four rules

**1. Schedule on event, cancel on event.** Never poll for "who needs a review request?" The
`EngagementCompleted` event schedules `review_request` (+2h) and `review_reminder` (+3d). The
`ReviewSubmitted` event cancels both by `dedupe_key` prefix. A follow-up that fires after its
reason evaporated is worse than none — it tells the user you aren't paying attention.

**2. Budget the channel.** Before sending, check `comms_log`:

```php
// Per user, per channel, rolling window.
'in_app'   => unlimited,
'push'     => 4 per day,
'sms'      => 2 per day,  3 per week,   // costs money, and it's THEIR money on some plans
'whatsapp' => 3 per day,
'email'    => 2 per day,
```

Over budget → `status = 'suppressed'`, not sent. Log it. A suppressed follow-up is a *product
signal*: if you're routinely suppressing, you're sending too much.

Transactional messages (`check_in_overdue`, `auto_approve_warning`, `payout_ready`) bypass the
budget. Marketing-ish ones (`reengagement`, `maintenance_due`) never do.

**3. Consent is per-purpose.** Doc 04: under Law No. 2024/017 consent is the lawful basis and
there's no legitimate-interest escape. So `reengagement` and `maintenance_due` require a
`marketing` consent grant; `check_in_overdue` doesn't, because it's service delivery. Check
`consents` before every non-transactional send, and honour revocation immediately. Getting this
wrong is a regulatory problem, not just an annoyance.

**4. Every follow-up has a response action.** If the message doesn't have a single obvious
next tap — *Approve quote*, *Leave review*, *Book again* — don't send it. Deep-link it, record
`response_action` when tapped, and use the response rate to kill the ones that don't work.

## Channel strategy for Cameroon

**WhatsApp is the channel.** Not email — email penetration is low and open rates will be
dismal. Not SMS as the default — it costs you per message and gets ignored. WhatsApp is where
these conversations already happen, and a WhatsApp Business API template with a deep link back
into the app is the highest-response follow-up you will ship.

Routing ladder, cheapest and least intrusive first:

```
in_app  → always written
push    → if the device has a live token
whatsapp→ if opted in and push unacknowledged after 30m   ← the workhorse
sms     → transactional only, or if no WhatsApp
email   → receipts, invoices, and records. Not for nudges.
```

WhatsApp Business API requires pre-approved templates for business-initiated messages outside
the 24-hour service window. That's a lead time — **start template approval early**, alongside
the MTN MoMo KYC (doc 03), because both are external dependencies with human queues.

Voice notes: the workspace supports them (doc 06) and this market uses them heavily. Follow-ups
stay text — a robot voice note is uncanny.

## Client-facing CRM surface

Providers need to *see* this, not just be subject to it. In the `pro` app and the provider web
dashboard:

- **Customer list** with job history, lifetime value, last contact, repeat count.
- **Pipeline view**: quotes awaiting response, jobs awaiting approval, warranties expiring.
- **Manual follow-up**: let a provider schedule their own nudge on a customer. Same table,
  `kind='reengagement'`, `created_by_user_id` set, same budget rules — a provider spamming a
  customer through your platform is your reputation problem.
- **Do-not-contact**, honoured absolutely, per customer.

This is the feature that makes a provider's reputation and client book feel like *an asset held
on your platform* — which is the anti-leakage argument that actually works, better than any
policing. Their customer list, their history, their warranty tracking, their repeat business.
Leaving means abandoning it.

## Testing

- Idempotency: fire the same domain event 50×, assert exactly one `follow_ups` row.
- Cancellation: complete → review submitted → assert both review follow-ups `cancelled`.
- Budget: 5 SMS in a day → assert 2 sent, 3 `suppressed`.
- Consent: revoke `marketing` → assert `reengagement` suppressed, `check_in_overdue` still sent.
- Ordering: assert no follow-up fires for an engagement that was cancelled before its
  `scheduled_for`.
