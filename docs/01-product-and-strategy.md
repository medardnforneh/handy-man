# 01 — Product and strategy

This document exists because three strategic decisions constrain the code more than any
technical choice does. If these change, large parts of the schema change with them.

## 0. What we are NOT building

Source documents in circulation describe three different products. Only one is ours.

| Vision | What it is | Verdict |
|---|---|---|
| **Marketplace + collaboration platform** | Customers discover, hire, collaborate with, pay, review providers. On-site, remote, or hybrid. | ✅ **This is the product.** |
| Marketplace + FSM | Companies assign staff, dispatch, report | ✅ Subsumed — a company is a provider party |
| Single-business SaaS | A website + booking + CRM + invoicing sold *to* one handyman business (~€99/yr, agency model, iDEAL payments, Dutch/Polish i18n) | ❌ **Not this.** Different customer, different market, different stack |

The third is a real business and a tempting one — it's easier to sell and needs no liquidity —
but it is a **product pivot, not a feature**. It has one tenant per deployment, no two-sided
trust problem, no leakage, no escrow, and no dispatch. If you want it, decide deliberately;
don't drift into it by accepting its feature list.

**What we took from it anyway:** client follow-ups and CRM (→ `docs/07`), warranty tracking,
review-request automation, quality checklists, WhatsApp as a first-class channel. Those are
good ideas regardless of business model. **What we rejected:** its stack (React/Supabase/
Vercel/Stripe — none of which survives contact with Cameroon), its i18n set, and its
economics.

## 1. This is two products wearing one coat

| | Marketplace | Field service management |
|---|---|---|
| Archetype | TaskRabbit, Thumbtack, Airtasker | Jobber, ServiceTitan, ServicePower |
| Core loop | Match stranger to stranger, build trust, take a cut | Dispatch known staff, track work, invoice |
| Hard problem | Liquidity, trust, leakage | Scheduling, routing, reporting, payroll |

"A company provides services and assigns people to perform the job and report" is the FSM
product. "Individuals find and book a handyman" is the marketplace product. Most platforms do
one. Doing both is legitimate — it's roughly what Jobber's marketplace ambitions and Urban
Company's pro-teams model each reach for — but be clear-eyed: it is two backlogs.

**The unification trick.** Do not build two systems. Note that:

> An individual provider is a company with exactly one employee.

So model the *assignment* layer universally. Every accepted job produces an **engagement**
(the contract between customer party and provider party) and one or more **assignments**
(the humans actually doing it). For a solo provider, the system auto-creates a single
assignment pointing at themselves. For a company, a dispatcher creates one or many.

The payoff: the worker mobile app has exactly one concept — "my assignments" — and never needs
to know whether the person holding the phone is self-employed or on staff. Reporting,
check-in/out, photo evidence, and time tracking all hang off `assignments`, uniformly. This is
the single most important structural decision in the build.

## 2. Revenue model determines whether escrow is on the critical path

Two proven archetypes, and they are not a style choice:

- **Commission / transactional** (TaskRabbit, ~15% on the booking). Platform owns the payment.
  Requires escrow. Revenue scales with GMV. Rich data, strong retention.
- **Pay-per-lead / subscription** (Thumbtack). Providers buy credits to quote. Platform
  monetises the *connection*, not the transaction. Revenue arrives before the job happens.

The industry framing: Thumbtack monetises connections, TaskRabbit monetises transactions.

### Why this matters more here than in the US

**Leakage** (disintermediation) is when the two sides meet on your platform and then transact
off it. Published research documented disintermediation threatening up to ~18% of transactions
on some marketplaces; operators self-report revenue losses anywhere from 30% to 80%. It hits
service marketplaces hardest, because the procurement conversation naturally leaks contact
details, and it is worst on *repeat* bookings.

Now add local reality: a cash-and-MoMo economy where exchanging a phone number is the default
social protocol and MoMo person-to-person transfer is instant, free-ish, and universal. A
commission model asks the customer to prepay a stranger through your app when they could
simply hand over cash or MoMo the pro directly. Your commission is a tax on a step they can
trivially skip.

### Recommendation

**Lead-credits first, escrow second — but build the ledger for both from day one.**

- **Phase A revenue:** providers buy lead credits (or a subscription) to send offers/bids.
  Revenue does not depend on capturing the transaction, so leakage stops being existential. A
  job paid in cash off-platform still earned you the lead fee.
- **Phase B revenue:** offer **Protected Payment** as an opt-in premium — escrowed funds,
  dispute resolution, guarantee. Sell it as trust, not as a toll. Customers who want
  protection pay for it; the rest still generate lead revenue.

Note that lead credits *are* money. So the double-entry ledger is Phase 1 regardless of which
revenue model wins. This is why the ledger is not deferrable.

### Anti-leakage measures worth building (in priority order)

1. Make staying easier than leaving: one-tap rebooking of a known pro, saved addresses,
   job history. Convenience beats policing.
2. Fast payouts. Providers leave platforms over payout speed more than over fee percentage.
3. Escrow + dispute resolution as a genuine customer benefit.
4. Review gating — reputation only accrues for on-platform jobs. A pro's rating is an asset
   they lose access to by going around you.
5. In-app messaging with contact-detail detection (flag, don't hard-block early — false
   positives on a small marketplace kill liquidity).
6. Only then: terms-of-service penalties. Policing without a value proposition alienates the
   supply side, which is the scarce side.

**Do not hard-block phone number sharing at launch.** With low liquidity you need every match
to succeed. Detect and measure first; enforce once you have supply density.

## 3. Actors

| Actor | Is a `party` | Notes |
|---|---|---|
| Individual customer | ✅ `kind=individual` | Also has a `user` login |
| Individual provider | ✅ `kind=individual` | Same person can be both customer and provider |
| Company customer | ✅ `kind=organization` | e.g. a property manager booking repairs |
| Company provider | ✅ `kind=organization` | Assigns staff |
| Worker | ❌ | A `user` with a `membership` in an organization. Also has their own individual party (can moonlight). |
| Dispatcher | ❌ | A `user` with `membership.role=dispatcher` |
| Platform admin | ❌ | A `user` with an admin role, Filament access only |

**One `users` table.** Do not split customer/provider users. In this market the same person is
routinely both — a plumber hires a painter. Splitting means duplicate identity, duplicate auth,
duplicate reputation, and a migration later.

**Party supertype.** `parties` is the thing that owns jobs, offers, money, and reputation.
`users` and `organizations` each specialise it 1:1. This gives real foreign keys instead of
polymorphic `*_type/*_id` columns, and means `jobs.customer_party_id` works identically whether
the customer is a person or a company.

## 4. Assignment modes

Three modes, one mechanism. `job_offers.origin` distinguishes them:

| Mode | `origin` | Who creates the offer | Liquidity needed |
|---|---|---|---|
| Direct booking | `customer_direct` | Customer picks a pro | None — works from day one |
| Dispatch | `system_dispatch` | Ranking engine fans out | Medium supply |
| Bidding | `provider_bid` | Providers quote an open job | High supply |

`jobs.mode` locks a job to one mode so it can't mix bidding and dispatch mid-flight.

**Ship direct booking first.** Bidding with ten providers is a ghost town; dispatch with ten
providers auto-assigns nobody. Build the schema for all three, expose one, enable the others
by config flag as supply grows.

## 5. Scope

### Now in scope (from the vision doc — these are not optional)

- **Remote and hybrid engagements.** Geography is conditional. → `docs/06`
- **Engagement workspace**: chat, voice notes, structured actions, timeline, media, documents
- **Versioned quotations** and negotiation as a phase
- **Milestones, deposits, staged payments** — the ledger already supports this
- **Deliverables** for remote work
- **Warranty** + claims + remedy jobs
- **Client follow-ups / CRM** → `docs/07`
- **Cash as a recorded payment method.** Not ignored — *recorded*. If a job settles in cash,
  capture it. You cannot manage leakage you cannot see, and a provider who reports cash
  honestly should be rewarded, not punished.
- Trust metrics: completion rate, response time, on-time rate, repeat-customer rate

### Still out of scope for v1

- Route optimisation / multi-stop planning
- Payroll, timesheet export, accounting integrations
- Inventory and parts
- Recurring/subscription jobs (`maintenance_due` follow-ups cover the 80% case — `docs/07`)
- Multi-currency (XAF only)
- Insurance underwriting
- AI quote assistance, semantic search, fraud ML (vision doc lists these as future — agreed)
- Video calls (voice notes and photos first; WebRTC is a project, not a ticket)
- Elasticsearch, microservices, event sourcing, GraphQL

Each of these is a real product. None is why someone opens your app the first time.

### A note on Urban Company

Among the incumbents, the app-store ratings are unflattering and consistent: Angi ~3.0,
Handy ~3.2, Porch ~2.5, TaskRabbit ~3.9 — and **Urban Company ~4.7 on 10M+ downloads.**

Urban Company is the one that *curates supply* rather than running an open marketplace. That
gap is the strongest argument in this whole document for the phased plan: **fewer, verified,
well-matched providers beat a large open pool.** Resist the urge to grow supply fast. Your
verification queue is a feature, not a bottleneck to optimise away.

Also worth internalising: providers multi-home. "Many handymen use two or three apps to stay
busy year-round." Your supply will be on your competitors simultaneously. You are competing for
their *attention at the moment a job arrives*, which is a response-time and payout-speed
problem, not a signup problem.
