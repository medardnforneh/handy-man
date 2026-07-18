# 03 — Money and the ledger

## Why this document is long

In the US playbook you would use Stripe Connect: Stripe holds the money, runs the escrow,
manages sub-accounts, handles KYC, and does payouts. You'd write maybe 200 lines.

**Stripe does not support Cameroon merchants.** Not for payments — Stripe has expanded Tax
coverage into Cameroon, but not payment processing. The workaround people use is registering a
US entity via Stripe Atlas, which is a real option but a business decision with tax, legal, and
FX consequences, not an integration detail.

Assume local rails. That means:

- You collect via a mobile-money aggregator (MTN MoMo + Orange Money).
- **The money sits in your own merchant wallet.** There are no per-provider sub-accounts.
- Therefore *you* are the ledger of record. Nobody else is tracking who is owed what.

This is the single biggest deviation from the western playbook, and it is why the ledger is
Phase 1 rather than Phase 3.

## Gateway options

| Provider | Coverage | Notes |
|---|---|---|
| **CamPay** | MTN + Orange, Cameroon-focused | Collections, payouts, bulk disbursement, sandbox capped at ~100 XAF. Next-business-day settlement. |
| **PayUnit** | Cameroon-focused | Multi-method checkout aggregator. |
| **Monetbil** | Cameroon | Long-standing local option. |
| **CinetPay** | Pan-African | MoMo + Orange + cards in one API, plugins/SDKs. ~1.5–3.5% depending on method and volume. |
| **Flutterwave** | Pan-African | Broadest currency/method coverage; best if you expand beyond CM. |
| Direct MTN MoMo API | momodeveloper.mtn.com | KYC onboarding is slow — **start the application early**. Sandbox uses EUR. |
| Direct Orange Money Web Payment | developer.orange.com | Available to CM merchants. |

Underlying MoMo/OM operator fees run roughly 0.5–1.5%; aggregators layer on top to ~1.5–3.5%.

**Decision:** start with one aggregator that does *both* collections and disbursements
(CamPay or CinetPay), because you need payouts, not just checkout. Direct operator integration
is a later cost optimisation, not a starting point.

**Build the abstraction anyway.** You will switch providers. Assume it.

```php
interface PaymentGateway {
    public function requestCollection(CollectionRequest $r): GatewayResult;  // USSD push to payer
    public function requestPayout(PayoutRequest $r): GatewayResult;
    public function fetchStatus(string $externalRef): GatewayResult;
    public function verifyWebhook(Request $request): bool;
    public function parseWebhook(Request $request): GatewayEvent;
}
```

One implementation per provider under `app/Domain/Money/Gateways/`. Selected by config. The
rest of the app never names a provider.

## The reality of a MoMo collection

This is not a card charge. Understand the flow before designing around it:

1. You call the aggregator with an amount and the payer's MSISDN.
2. The operator pushes a **USSD prompt to the payer's physical phone**.
3. The payer types their PIN. Or doesn't. Or their phone is off. Or they're in a dead zone.
4. 30–90 seconds later — sometimes much longer — a webhook may arrive. Or may not.

Consequences you must design for:

- **`pending` is a long-lived, normal state.** Not an edge case. Your UI must live there
  gracefully — "check your phone", with a real countdown.
- **Never trust the webhook alone.** Fire a reconciliation poll against `fetchStatus` on a
  backoff schedule (10s, 30s, 60s, 2m, 5m…) until terminal or expiry. Webhooks get lost.
- **Never trust the poll alone either.** Whichever arrives first wins; the other must be a
  no-op. This is why `payment_events` has a uniqueness constraint.
- **Duplicate webhooks are routine.** Same event, delivered three times.
- **Timeout is a state, not an error.** Expire intents after ~15 minutes and let the customer
  retry with a *new* intent. Never reuse an intent.

## Ledger design

Double-entry. Not negotiable. Every movement is a balanced transaction.

```sql
ledger_accounts (
  id uuid PK,
  party_id uuid REFERENCES parties(id),   -- NULL for platform-owned accounts
  kind account_kind NOT NULL,
  currency char(3) NOT NULL DEFAULT 'XAF',
  created_at,
  UNIQUE NULLS NOT DISTINCT (party_id, kind, currency)
);

ledger_transactions (
  id uuid PK,
  kind txn_kind NOT NULL,
  reference_type text,        -- 'engagement' | 'payment_intent' | 'payout' | 'referral'
  reference_id uuid,
  occurred_at timestamptz NOT NULL DEFAULT now(),
  memo text,
  created_by_user_id uuid REFERENCES users(id),   -- set for manual adjustments
  created_at
);

ledger_entries (
  id uuid PK,
  transaction_id uuid NOT NULL REFERENCES ledger_transactions(id),
  account_id uuid NOT NULL REFERENCES ledger_accounts(id),
  direction entry_direction NOT NULL,
  amount_minor bigint NOT NULL CHECK (amount_minor > 0),
  created_at
);
CREATE INDEX ON ledger_entries (account_id, created_at DESC);
```

### The invariants, enforced by the database

```sql
-- 1. Append-only. Revoke the ability to lie about history.
REVOKE UPDATE, DELETE ON ledger_entries FROM app_user;
REVOKE UPDATE, DELETE ON ledger_transactions FROM app_user;

-- 2. Every transaction balances. Deferred so entries can be inserted within one txn.
CREATE CONSTRAINT TRIGGER ledger_must_balance
  AFTER INSERT ON ledger_entries
  DEFERRABLE INITIALLY DEFERRED
  FOR EACH ROW EXECUTE FUNCTION assert_transaction_balances();
```

```sql
CREATE OR REPLACE FUNCTION assert_transaction_balances() RETURNS trigger AS $$
DECLARE d bigint; c bigint;
BEGIN
  SELECT
    COALESCE(SUM(amount_minor) FILTER (WHERE direction='debit'), 0),
    COALESCE(SUM(amount_minor) FILTER (WHERE direction='credit'), 0)
  INTO d, c
  FROM ledger_entries WHERE transaction_id = NEW.transaction_id;

  IF d <> c THEN
    RAISE EXCEPTION 'Ledger transaction % unbalanced: debits=% credits=%', NEW.transaction_id, d, c;
  END IF;
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;
```

If an engineer (or an AI agent) ever writes an unbalanced transaction, Postgres refuses it. This
is worth more than any amount of code review.

### Balances are computed, never stored

```sql
CREATE VIEW ledger_balances AS
SELECT account_id,
       SUM(CASE WHEN direction='debit' THEN amount_minor ELSE -amount_minor END) AS balance_minor
FROM ledger_entries GROUP BY account_id;
```

Materialise it only when it measurably hurts, and refresh it from the entries. The entries are
the truth. If you cache a balance, the cache is a derived read model that must be rebuildable
from scratch, and you should have a test that rebuilds it and asserts equality.

> **Sign convention.** Pick one and write it at the top of `app/Support/Money.php`. Suggested:
> asset and expense accounts increase on debit; liability, equity, and revenue accounts increase
> on credit. `provider_payable` is a **liability** — you owe the provider — so it increases on
> credit. Get this wrong once and every report is wrong forever.

## Flows

Notation: `DR` debit, `CR` credit.

### Lead credit purchase (Phase A revenue)

Provider buys 10,000 XAF of lead credits via MoMo.

```
DR  platform_cash                 10,000
CR  lead_credit_liability(pro)    10,000
```
You now hold their cash and owe them credits.

### Lead credit spend

Provider sends a bid costing 500 XAF.

```
DR  lead_credit_liability(pro)       500
CR  platform_revenue                 500
```
The liability shrinks; you've earned it.

### Escrowed job — collection (Phase B)

Customer pays 20,000 XAF for a job.

```
DR  gateway_receivable            20,000     -- aggregator has it, you don't yet
CR  escrow_liability(engagement)  20,000     -- you owe this to someone, TBD
```

On aggregator settlement into your bank/wallet:

```
DR  platform_cash                 20,000
CR  gateway_receivable            20,000
```

Keeping `gateway_receivable` separate from `platform_cash` is what lets you reconcile against
the aggregator's statement and detect settlement gaps.

### Escrowed job — release on completion (15% fee)

```
DR  escrow_liability(engagement)  20,000
CR  provider_payable(pro)         17,000
CR  platform_revenue               3,000
```

### Payout to provider

```
DR  provider_payable(pro)         17,000
CR  platform_cash                 17,000
```
Only on gateway confirmation. If the payout fails, reverse with a new balanced transaction —
**never** delete the original. Corrections are new entries, always.

### Refund / dispute resolved for customer

```
DR  escrow_liability(engagement)  20,000
CR  platform_cash                 20,000
```

### Referral reward

Grant 2,000 XAF credit:
```
DR  platform_revenue               2,000     -- marketing cost, contra-revenue
CR  promo_liability(referrer)      2,000
```
Spend it:
```
DR  promo_liability(referrer)      2,000
CR  platform_revenue               2,000
```

## Payment tables

```sql
payment_intents (
  id uuid PK,
  party_id uuid NOT NULL REFERENCES parties(id),
  engagement_id uuid REFERENCES engagements(id),
  purpose text NOT NULL,               -- 'escrow' | 'lead_credits'
  gateway text NOT NULL,
  amount_minor bigint NOT NULL CHECK (amount_minor > 0),
  currency char(3) NOT NULL DEFAULT 'XAF',
  msisdn citext NOT NULL,
  status payment_status NOT NULL DEFAULT 'pending',
  external_ref text,
  idempotency_key text NOT NULL UNIQUE,
  ledger_transaction_id uuid REFERENCES ledger_transactions(id),
  initiated_at timestamptz NOT NULL DEFAULT now(),
  expires_at timestamptz NOT NULL,
  resolved_at timestamptz,
  failure_code text,
  raw jsonb,
  created_at, updated_at
);
CREATE UNIQUE INDEX ON payment_intents (gateway, external_ref) WHERE external_ref IS NOT NULL;

payouts (
  id uuid PK,
  party_id uuid NOT NULL REFERENCES parties(id),
  amount_minor bigint NOT NULL CHECK (amount_minor > 0),
  currency char(3) NOT NULL DEFAULT 'XAF',
  msisdn citext NOT NULL,
  gateway text NOT NULL,
  status payment_status NOT NULL DEFAULT 'pending',
  external_ref text,
  idempotency_key text NOT NULL UNIQUE,
  ledger_transaction_id uuid REFERENCES ledger_transactions(id),
  requested_at timestamptz NOT NULL DEFAULT now(),
  resolved_at timestamptz,
  failure_code text,
  raw jsonb,
  created_at, updated_at
);

payment_events (
  id uuid PK,
  gateway text NOT NULL,
  external_ref text NOT NULL,
  event_type text NOT NULL,
  signature_valid boolean NOT NULL,
  payload jsonb NOT NULL,
  received_at timestamptz NOT NULL DEFAULT now(),
  processed_at timestamptz,
  UNIQUE (gateway, external_ref, event_type)   -- THE duplicate-webhook defence
);
```

That `UNIQUE (gateway, external_ref, event_type)` is what makes replayed webhooks harmless.
Insert the event first; if the insert conflicts, you've already seen it — return 200 and stop.

## Webhook handler shape

```php
public function handle(Request $request, string $gateway)
{
    $impl = Gateways::make($gateway);

    // 1. Verify signature BEFORE parsing. Reject unsigned callbacks.
    if (! $impl->verifyWebhook($request)) {
        PaymentEvent::create([... 'signature_valid' => false]);
        return response()->noContent(401);
    }

    $event = $impl->parseWebhook($request);

    // 2. Deduplicate by insert. Conflict = already processed.
    try {
        $row = PaymentEvent::create([
            'gateway' => $gateway, 'external_ref' => $event->ref,
            'event_type' => $event->type, 'signature_valid' => true,
            'payload' => $event->raw,
        ]);
    } catch (UniqueConstraintViolationException) {
        return response()->noContent(200);   // already seen — success, not error
    }

    // 3. Apply inside a transaction, locking the intent.
    DB::transaction(function () use ($event, $row) {
        $intent = PaymentIntent::where('external_ref', $event->ref)
            ->lockForUpdate()->firstOrFail();

        if ($intent->status->isTerminal()) return;   // late webhook after poll won. Fine.

        ApplyGatewayResultAction::handle($intent, $event);  // writes the ledger txn
        $row->update(['processed_at' => now()]);
    });

    return response()->noContent(200);
}
```

**Always return 200 for events you've already handled.** A non-200 makes the gateway retry
forever, and you will spend a weekend on it.

## Reconciliation

Non-optional. Nightly job:

1. Pull the aggregator's settlement report for the day.
2. Assert `SUM(platform_cash)` from your ledger matches the actual wallet balance.
3. Any `payment_intent` stuck in `pending`/`processing` past its expiry → force `fetchStatus`.
4. Any discrepancy → an admin alert and a row in a `reconciliation_exceptions` table. Never
   auto-correct a discrepancy. A human decides, and the correction is a balanced adjustment
   transaction with `created_by_user_id` set.

The day you cannot answer "does our ledger match the bank?" in under a minute is the day the
platform stops being trustworthy.

## Testing requirements

- Property test: for any random sequence of flows, `SUM(debits) == SUM(credits)` globally.
- Concurrency test: N simultaneous webhook deliveries for the same `external_ref` produce
  exactly one ledger transaction.
- Test that a failed payout reversal leaves `provider_payable` at its pre-payout value.
- Test that `UPDATE ledger_entries` throws at the DB level, not just the app level.
