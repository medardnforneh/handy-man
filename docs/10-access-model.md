# 10 — Access model: open navigation, fact-gated actions

This is the doc that most contradicts a normal "roles and permissions" build. Read it before
writing any authorization code, or you will reach for a role matrix that this design deliberately
does not use.

## The core principle

**Access is open. Trust is earned. They are different things.**

- **Navigation is never gated by role.** One app, one login, one identity. Both a customer
  section and a provider section exist and are fully visible to *every* logged-in user, from the
  first second, with nothing to unlock. A pure customer simply uses the customer section and
  ignores the other. A pure provider does the reverse. Someone who does both uses both — with no
  switch, no "become a provider" step, no mode toggle, no extra grant.
- **A small number of high-stakes *actions* gate on verified facts** — not on roles. The gate
  checks a fact about the user (`identity is verified`, `a payout method exists`, `a skill is
  listed`), and it's satisfied *inline*, at the moment the user first hits the action.

You "become" a provider by *using* the provider section — listing a skill, quoting a job. The
provider identity is a **consequence** of behaviour, not a **prerequisite** granted in advance.
This is how open marketplaces work: eBay doesn't make you apply to become a seller; you list an
item and you are one.

## What this explicitly is NOT

- ❌ Not role-gated navigation. There is no "if user has role `provider`, show provider tab."
  The provider tab is always shown.
- ❌ Not empty-state-by-role. An earlier draft showed an empty provider section to
  non-providers. Wrong. The section is fully present and usable for everyone.
- ❌ Not a mode switch. No Uber-style driver/passenger toggle. Both sections coexist.
- ❌ Not "apply and get approved to provide." No admin grants a capability before use.
- ❌ Not a role matrix checked at the section boundary.

## What roles ARE still for

Spatie roles/permissions stay in the system, but their job shrinks to two legitimate uses:

1. **Organization-internal roles** (doc 01/02): `owner`, `dispatcher`, `finance`, `worker`
   within a *company* provider. These genuinely gate actions — a worker can't reassign a job; a
   dispatcher can. This is real RBAC and Spatie is right for it.
2. **Staff/admin roles**: `superadmin`, `support`, `verifier`, `finance_admin` for the Filament
   panel. Also real RBAC.

**Roles do NOT gate the customer-section / provider-section split for an ordinary user.** That
split is not a permission at all — both are always open.

## Capabilities gate on facts, not roles

The pattern that keeps the whole codebase honest:

> A capability names the **fact** it requires and checks it at the moment of the action,
> prompting the user to satisfy it right there.

Model capabilities explicitly, as derived facts about the user/party — never as pre-granted
permissions:

```
identity_verified     ← verification_documents approved to the required tier
has_payout_method     ← a MoMo payout number saved & confirmed
skill_listed          ← at least one provider_skill exists
has_provider_profile  ← provider_profiles row exists
payment_method_ready  ← a customer has a usable payment path (may be just "MoMo prompt")
org_member(role)      ← membership in the acting organization with the given role
```

Each guarded action declares its precondition. Examples:

| Action | Required fact | If missing → |
|---|---|---|
| Browse either section | *(none)* | always allowed |
| Create a provider profile | *(none)* | always allowed — this is how you start |
| List a skill | `has_provider_profile` | inline: create profile first (one tap) |
| Submit a quote / bid | `skill_listed` | inline: list the skill |
| **Accept a paid job** | **`identity_verified` (tier per job)** | **inline: start verification** |
| Receive a payout | `has_payout_method` | inline: add MoMo number |
| Be dispatched a job | `identity_verified` + `skill_listed` | silently not ranked until satisfied |
| Post a job (customer) | *(none at browse/post)* | always allowed |
| Pay for an escrowed job | `payment_method_ready` | inline: MoMo prompt |

Implement as **policy/guard objects**, one per capability, each checking its fact — not as role
checks scattered through controllers. The API returns a structured "precondition not met" error
naming the missing fact and a deep link to satisfy it, so the app can prompt inline rather than
dead-ending. This is a first-class response shape, not an afterthought:

```jsonc
// 409-style, machine-readable — the app renders the right inline prompt
{ "error": "precondition_unmet",
  "capability": "accept_paid_job",
  "missing_fact": "identity_verified",
  "required_tier": 2,
  "resolve": { "type": "verification", "deep_link": "/provider/verify" } }
```

## The verification gate keys on engagement_mode

Because remote and hybrid jobs are in v1 (doc 06), the identity gate is **not** one flat rule.
Physical presence is what raises the stakes, so:

| Job `engagement_mode` | To accept a paid job you need | Safety apparatus |
|---|---|---|
| `onsite` | Full ID verification (tier 2+; tier 3 for high-risk skills) | check-in/out, panic, share-my-job, address reveal — all on |
| `hybrid` | Full ID verification (someone still visits) | on for the on-site portions |
| `remote` | **Lighter identity check** — confirmed phone + basic profile; no national-ID home-visit tier | no check-in, no panic; deliverables + escrow instead |

Gating a remote website-consult behind national-ID capture is friction with **no** safety
payoff — the provider never enters anyone's home. Gating an on-site electrical job behind it is
exactly the trust signal that makes a customer willing to open the door. Same principle, mode
decides the strength. Encode this in the `AcceptPaidJob` capability as a function of the job's
mode and the skill's `risk_tier`, reading the tier requirement from doc 04's tier table.

Customer-side gates are lighter throughout, because booking is lower-risk than being admitted to
a home — but the same fact-gated pattern applies (e.g. a customer requesting an after-hours
on-site visit may be asked to reach a basic verification tier, per doc 04).

## Why this is more secure than a role matrix

A role is broad and easy to over-grant; the failure mode is a role that accidentally carries a
capability it shouldn't. A fact-gate is narrow and sits on the dangerous *action* itself. You
cannot accept a paid on-site job without `identity_verified` being true, no matter what roles you
hold, because the check is on the action, not on a role that might have been mis-assigned. The
sensitive thing guards itself.

## Build implications

- **No role check gates the section split.** If a task says "show provider tab only to
  providers," it's wrong — reject it.
- **Every guarded action is a capability/guard object** naming its fact. New sensitive action →
  new capability, not a new role.
- **`precondition_unmet` is a first-class API response** with `missing_fact` + `resolve` deep
  link. The app always turns it into an inline prompt, never a dead end.
- **Facts are derived and cached, recomputed on the events that change them** (verification
  approved, payout method added, skill listed) — same outbox discipline as everywhere else.
- **Spatie is scoped to org-internal roles and staff/admin roles only.** Do not use it for the
  customer/provider split.
- Test: a brand-new account can open both sections fully; can start a provider profile with zero
  prior grants; is blocked from *accepting an on-site paid job* until verified, with the inline
  prompt returned; and can accept a *remote* job under the lighter check.
