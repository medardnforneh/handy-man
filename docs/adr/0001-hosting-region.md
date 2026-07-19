# ADR 0001 — Hosting region

- **Status:** ACCEPTED (Option A — host in-country / Cameroon) — pending final lawyer sign-off
- **Date:** 2026-07-18 (decided 2026-07-19)
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

> **Outcome:** the decision went to **Option A (in-country)** — prioritising compliance simplicity
> (no cross-border transfer) over managed-stack convenience. The analysis below is retained as the
> reasoning; see "Decision" for what was chosen.

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

**Chosen: Option A — host in-country (Cameroon).** Personal data of Cameroonian data subjects
stays inside Cameroon, so there is **no cross-border transfer** — the cleanest posture under Law
No. 2024/017 and the strongest story for the CNDP. We accept the operational cost of running a
self-managed stack in exchange for that compliance simplicity.

> Decision record (confirm remaining items with counsel):
>
> - Chosen region/provider: **In-country (Cameroon)** — target a national/local IaaS (e.g. Camtel
>   data centre or a comparable Cameroonian provider); pick the specific vendor during infra setup.
> - Object-storage region: **In-country**, S3-compatible via self-hosted **MinIO** (or the
>   provider's S3-compatible offering) so the app keeps using the S3 filesystem driver unchanged.
> - CNDP: no cross-border **transfer** authorisation needed; the **processing register** and any
>   **prior processing authorisation** (doc 04 §4 point 2) still apply — start that on day 1.
> - Confirmed by (name, date): `__________________` (lawyer sign-off still required)

## Consequences

- **We run the managed pieces ourselves in-country:** PostgreSQL 16 + PostGIS, Redis, and an
  S3-compatible object store (MinIO). This raises the ops burden — backups, HA/replication,
  patching, and disaster recovery are now our responsibility. Budget for it and verify **power and
  network resilience** at the chosen data centre before launch.
- **No cross-border transfer authorisation is required**, which removes a long legal lead time.
  The CNDP processing register (and prior authorisation for processing, if required) is still a
  founder+lawyer task and still gates launch.
- Keep the deployment **provider-agnostic**: S3-compatible storage via the filesystem abstraction,
  plain Postgres/Redis URLs, region/provider as configuration — so if the in-country choice proves
  operationally unworkable we can move with minimal code change (revisit this ADR if so).
- The crypto-shred + no-PII-in-ledger design (doc 04) still stands and further limits exposure.
- Until the environment is provisioned and the processing register is filed, **no real personal
  data on disk** — local dev uses synthetic data only.
