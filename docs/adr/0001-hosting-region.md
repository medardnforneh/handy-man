# ADR 0001 — Hosting region

- **Status:** PROPOSED — awaiting founder + lawyer confirmation (see "Decision" below)
- **Date:** 2026-07-18
- **Deciders:** Founder / data controller, with Cameroonian legal counsel
- **Task:** P0-09 (build plan) — a **blocker**. Everything after it writes personal data to disk.
- **Related:** `docs/04-security-and-trust.md` §4 (Law No. 2024/017), CLAUDE.md rule #6 (PII
  minimisation), the erasure-vs-ledger crypto-shred design.

> **This ADR is not legal advice.** The region choice is a legal decision under Cameroonian law
> and must be confirmed with a Cameroonian lawyer before we provision anything that stores
> personal data. This document exists so that decision is made deliberately and on the record.

## Context

Cameroon **Law No. 2024/017** on the protection of personal data is in force (18-month
transition expired **23 June 2026**). Points that bind the region choice:

1. **Cross-border transfer of Cameroonian personal data requires CNDP approval.** Hosting outside
   Cameroon — including "familiar" defaults like AWS `eu-west-1` — *is* a cross-border transfer.
2. **Consent, not legitimate interest, is the lawful basis.** No "we need it to run the service"
   justification is available.
3. **Extraterritorial scope**: the Act reaches data subjects located in Cameroon regardless of
   where we incorporate or host.
4. Migrating a live **PostGIS** database + an **encrypted document bucket** across regions later
   is genuinely painful — so we resolve this *before* choosing, not after.

What the stack needs from a region (see CLAUDE.md stack table):

- Managed or self-managed **PostgreSQL 16 with PostGIS** (geo is core).
- **Redis** (cache/queue/Horizon).
- **S3-compatible object storage** (media, encrypted verification documents) — region matters
  legally for the bucket too, not just the DB.
- Reliable power/network and a credible operational story (this serves a live marketplace).
- Ideally low latency to Cameroonian users on low-end Android over mobile networks.

## Options considered

### Option A — Host in-country (Cameroon)
Provider examples: Camtel national data centre / local IaaS.
- **+** No cross-border transfer → the cleanest compliance posture; strongest regulator story.
- **+** Lowest in-country latency.
- **−** Limited/absent **managed** PostgreSQL+PostGIS, Redis, and S3-compatible object storage —
  likely self-managed on raw VMs, raising ops burden and reliability risk.
- **−** Thinner tooling/observability ecosystem; power/network resilience must be verified.

### Option B — Regional Africa, outside Cameroon (e.g. AWS `af-south-1` Cape Town)
- **+** Data stays on the African continent; mature managed services (RDS PostgreSQL+PostGIS, S3,
  ElastiCache).
- **−** Still a **cross-border transfer** → CNDP authorisation + safeguards still required.
- **−** Latency to Cameroon worse than in-country; no material *legal* saving vs Option C.

### Option C — EU host with mature managed stack (OVHcloud or Scaleway, FR/EU regions)
- **+** Mature managed **PostgreSQL + PostGIS**, Redis, and **S3-compatible** object storage
  (OVHcloud/Scaleway both offer S3-compatible buckets) — best fit for the pinned stack.
- **+** Good price/performance; strong operational maturity; not a US hyperscaler (simpler
  contractual/data-governance story than AWS/GCP for some counsel).
- **−** **Cross-border transfer** → CNDP authorisation + contractual safeguards + explicit
  consent required. Transfer paperwork has lead time.

### Option D — US hyperscaler default (AWS `eu-west-1`, GCP, Azure)
- The path `docs/04` explicitly warns against defaulting into.
- Same cross-border burden as C, with a heavier vendor and (for some counsel) a harder transfer
  story. **Rejected** unless a specific managed capability forces it.

## Recommendation (to confirm)

Two-track, because the CNDP process has a long lead time and must start on day 1 regardless:

1. **Start the CNDP processing register + cross-border transfer authorisation immediately**
   (founder + lawyer task, already on the launch checklist). This unblocks Options B and C.
2. **Provisionally target Option C (EU — OVHcloud, FR region) with contractual transfer
   safeguards and explicit, language-tagged consent**, *unless* legal counsel judges in-country
   (Option A) both compliant-simpler *and* operationally viable for the managed stack we need.
   Option A is preferable on pure compliance if — and only if — a provider can deliver
   PostGIS + object storage + Redis at acceptable reliability.

To keep the decision reversible-ish:
- **Do not hard-code the provider** in application code. Use S3-compatible storage via the
  filesystem abstraction and standard Postgres/Redis URLs, so the region can move if the legal
  answer changes.
- Lean on the **crypto-shred + no-PII-in-ledger** design (`docs/04`) so the *legal surface* of
  what actually crosses a border is minimised: the ledger carries no PII; identifiers are
  encrypted per-party.

## Decision

> **PENDING.** Fill in once confirmed with counsel:
>
> - Chosen region/provider: `__________________`
> - CNDP authorisation status: `__________________`
> - Object-storage region: `__________________`
> - Confirmed by (name, date): `__________________`

## Consequences

- Until this is decided and CNDP authorisation is at least *applied for*, we do **not** provision
  any environment that stores real personal data. Local dev uses synthetic data only.
- `.env`/deploy config will expose region/provider as configuration, never as code constants.
- The processing register (founder task) references this ADR as the record of the region rationale.
