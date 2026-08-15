import { Injectable } from '@angular/core';
import { environment } from '../../environments/environment';
import { loadDeviceId } from '../core/device';
import { uuid } from '../core/uuid';
import { api } from './client';
import { tokenStore } from './token-store';

/** One line of a submitted quotation (P2.5-01). The server totals these; it never trusts a total. */
export interface QuotationLineInput {
  kind: 'labour' | 'material' | 'travel' | 'other';
  label: string;
  quantity: number;
  unitPriceMinor: number;
}

/** A quotation to submit against a job (P2.5-01). `validUntil` must be in the future. */
export interface QuotationInput {
  lines: QuotationLineInput[];
  depositMinor?: number;
  notes?: string;
  /** ISO date or datetime; the API requires it and rejects anything not after now. */
  validUntil: string;
}

/** The structured status signals a worker may emit (P5-06). `arrived` is the server's, via check-in. */
export type ProviderStatusSignal = 'on_the_way' | 'started' | 'paused' | 'resumed' | 'completed';

/** One line of materials used, priced per unit in minor units. */
export interface JobReportMaterial {
  label: string;
  qty: number;
  unitCostMinor: number;
}

/** A before/after photo for the report. The server strips its EXIF and records the geo in the DB. */
export interface JobReportPhoto {
  file: File | Blob;
  kind: 'before' | 'after';
}

/** The on-site job report a worker submits (P5-04). */
export interface JobReportInput {
  summary: string;
  extraChargesMinor?: number;
  materials?: JobReportMaterial[];
  photos?: JobReportPhoto[];
}

/**
 * The kinds of verification document (P6-01, doc 04). The kind fixes the tier the document works
 * toward — ID front/back and a selfie build to tier 2, the licence/company papers to tier 3 — so
 * this is not a free-text label the client gets to choose the meaning of.
 */
export type VerificationDocKind =
  | 'national_id_front'
  | 'national_id_back'
  | 'selfie'
  | 'trade_license'
  | 'insurance_cert'
  | 'rccm'
  | 'niu';

/**
 * What a dispute is ABOUT (P6-06). A closed set, because the category is what routes the case to
 * whoever handles it — a free-text reason would arrive nowhere in particular. Either party may use
 * any of them: `payment` is usually the provider's complaint and `quality` usually the customer's,
 * but the endpoint does not care which side is speaking and neither does this list.
 */
export type DisputeCategory = 'quality' | 'payment' | 'no_show' | 'scope' | 'safety' | 'other';

/**
 * What somebody did about a follow-up (P7-02). A closed set the server validates, because these are
 * the measurements that decide which nudges survive — a free-text answer would count as nothing.
 */
export type FollowUpAction =
  | 'quote_accepted'
  | 'review_submitted'
  | 'warranty_claimed'
  | 'rebooked'
  | 'approved'
  | 'dismissed'
  | 'opened';

/**
 * The DI surface for real API calls — a thin, typed wrapper over the generated `openapi-fetch`
 * client. Screens keep talking to their fixture services for now; those services will delegate to
 * this one method-by-method as endpoints are wired, so the migration is incremental and no screen
 * has to move. Every method surfaces `{ data, error }` as a resolved value or a thrown error, so
 * callers get typed data or a single failure path.
 */
@Injectable({ providedIn: 'root' })
export class ApiService {
  /** Liveness + contract metadata — unauthenticated. Confirms the app can reach the API (P0-08). */
  async meta() {
    const { data, error } = await api.GET('/meta');
    if (error) {
      throw error;
    }
    return data;
  }

  /** Public bilingual skills taxonomy (P1-07) — top-level categories, each with its leaves. */
  async skills(locale: 'fr' | 'en') {
    const { data, error } = await api.GET('/skills', { params: { query: { locale } } });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Browse providers by trade — the discover rail. Public: no session needed, which is the point
   * (a customer judges the marketplace before signing up). When a Bearer IS present the server
   * honours that user's blocks.
   */
  async browseProviders(options: { skill?: string; mode?: 'onsite' | 'remote'; locale?: 'fr' | 'en'; limit?: number } = {}) {
    const { data, error } = await api.GET('/providers', {
      params: {
        query: {
          skill: options.skill,
          mode: options.mode,
          locale: options.locale,
          limit: options.limit,
        },
      },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * One provider's public card, keyed by party. 404s for a suspended or blocked provider, which is
   * why the caller treats a failure as "no such provider" rather than "try again".
   */
  async provider(partyId: string, locale?: 'fr' | 'en') {
    const { data, error } = await api.GET('/providers/{party}', {
      params: { path: { party: partyId }, query: { locale } },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Full-text search over LEAF trades (P1-07b), in the matching language dictionary — searching
   * "plomberie" uses the French config, "plumbing" the English one. Public.
   */
  async searchSkills(q: string, locale: 'fr' | 'en') {
    const { data, error } = await api.GET('/skills/search', { params: { query: { q, locale } } });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** The signed-in user (P1-03) — requires the Bearer. Drives the account/profile identity. */
  async me() {
    const { data, error } = await api.GET('/auth/me');
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Persist the UI language server-side (P1-05b). Without this the app's language toggle and the
   * server's stored `locale` drift apart, and bilingual API payloads (skill labels) come back in a
   * different language than the chrome around them.
   */
  async setLocalePreference(locale: 'fr' | 'en') {
    const { data, error } = await api.PATCH('/me/preferences', {
      params: { header: { 'Idempotency-Key': uuid() } },
      body: { locale },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** Everything the platform holds about the caller — the right of access (DSAR, P1-10). */
  async dataExport() {
    const { data, error } = await api.GET('/me/data-export');
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * The right to erasure (P1-10). Crypto-shred: the party row and its id survive so the ledger's
   * foreign keys do, the data key is destroyed, and the human becomes unidentifiable. Irreversible
   * — there is no undo endpoint because there is no undo.
   */
  async eraseAccount() {
    const { error } = await api.DELETE('/me', {
      params: { header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
  }

  /**
   * Post a voice note (P4-05) — multipart audio, stored as a first-class `voice` message. An empty
   * recording comes back 422 `empty-upload` rather than failing at the database.
   */
  async postVoiceMessage(jobId: string, audio: Blob, filename: string, durationMs?: number) {
    const form = new FormData();
    form.set('audio', audio, filename);
    if (durationMs !== undefined) {
      form.set('duration_ms', String(Math.round(durationMs)));
    }

    const { data, error } = await api.POST('/jobs/{job}/voice-messages', {
      params: { path: { job: jobId }, header: { 'Idempotency-Key': uuid() } },
      body: form as never,
      bodySerializer: (body: unknown) => body as FormData,
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Fetch a media file as an object URL for playback.
   *
   * An `<audio src>` cannot carry the Bearer, and the media route is authorized — so the bytes are
   * fetched here with the token and wrapped in a blob URL the element can play. Callers must
   * revoke the URL when done or the blob leaks for the life of the page.
   */
  async mediaObjectUrl(url: string): Promise<string> {
    const response = await fetch(url, {
      headers: {
        Authorization: `Bearer ${tokenStore.get() ?? ''}`,
        'X-App-Version': environment.appVersion,
      },
    });
    if (!response.ok) {
      throw new Error(`media fetch failed: ${response.status}`);
    }

    return URL.createObjectURL(await response.blob());
  }

  /** The customer's own jobs (P2-03), newest first, with the compact engagement summary. */
  async jobs() {
    const { data, error } = await api.GET('/jobs');
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** One job with its full engagement summary + milestone list (owner view). */
  async job(id: string) {
    const { data, error } = await api.GET('/jobs/{job}', { params: { path: { job: id } } });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** Approve a milestone — releases its escrow slice to the provider (P3-10). Customer-gated. */
  async approveMilestone(milestoneId: string): Promise<void> {
    const { error } = await api.POST('/milestones/{milestone}/approve', {
      params: { path: { milestone: milestoneId }, header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
  }

  /** The signed-in customer's saved addresses (P1-06). */
  async addresses() {
    const { data, error } = await api.GET('/addresses');
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Save an address (P1-06). Gated on location_tracking consent (P1-05) — a 403 carries
   * `missing_purpose` rather than reading as a permission failure.
   *
   * The coordinates are not decoration: `addresses.point` is a `geography(Point,4326)` behind a
   * GIST index, and provider matching for on-site work is an ST_DWithin against it (P2-04). An
   * address without a real point is an address no provider can be matched to.
   */
  async createAddress(body: {
    label?: string;
    line1: string;
    quarter?: string;
    city: string;
    landmark_note?: string;
    latitude: number;
    longitude: number;
    country_code?: string;
  }) {
    const { data, error } = await api.POST('/addresses', {
      body,
      params: { header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** Create a job (draft) — mirrors CreateJob (P2-03). Returns the created job. */
  async createJob(body: {
    skill_id: string;
    engagement_mode: 'onsite' | 'remote' | 'hybrid';
    title: string;
    description?: string;
    address_id?: string;
    budget_minor?: number;
  }) {
    const { data, error } = await api.POST('/jobs', {
      body,
      params: { header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** Publish a draft job (draft → open) so providers can be found (P2-03). */
  async publishJob(id: string): Promise<void> {
    const { error } = await api.POST('/jobs/{job}/publish', {
      params: { path: { job: id }, header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
  }

  /** The workspace conversation thread — participants only (P4-01). Structured kinds are server-narrated. */
  async messages(jobId: string) {
    const { data, error } = await api.GET('/jobs/{job}/messages', {
      params: { path: { job: jobId } },
    });
    if (error) {
      throw error;
    }
    // `meta.engagement_id` names the live channel: the thread is keyed by the job, the channel by
    // the engagement (P4-03/04). `conversation_id` is what marks the thread read.
    return {
      messages: data.data,
      engagementId: data.meta?.engagement_id ?? null,
      conversationId: data.meta?.conversation_id ?? null,
    };
  }

  /**
   * The signed-in user's conversations — the messages tab. Membership decides what is listed, the
   * same gate the thread itself uses, so nothing can appear here that the user couldn't then open.
   */
  async conversations() {
    const { data, error } = await api.GET('/conversations');
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** Mark a conversation read (clears its unread badge). Forward-only and idempotent server-side. */
  async markConversationRead(conversationId: string): Promise<void> {
    const { error } = await api.POST('/conversations/{conversation}/read', {
      params: { path: { conversation: conversationId }, header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
  }

  /** Post a free-form text message to the thread (P4-02). Structured kinds are rejected server-side. */
  async postMessage(jobId: string, body: string) {
    const { data, error } = await api.POST('/jobs/{job}/messages', {
      params: { path: { job: jobId }, header: { 'Idempotency-Key': uuid() } },
      body: { body },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Providers matched to a job (P2-04) — geo-filtered for on-site/hybrid, the whole skilled pool for
   * remote. Owner-only. The resource is PII-minimised (headline + reputation, never the person's name).
   */
  async jobProviders(jobId: string) {
    const { data, error } = await api.GET('/jobs/{job}/providers', {
      params: { path: { job: jobId } },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** A provider's display-safe rolling metrics (P6-12) — on-time rate is null below the sample floor. */
  async providerMetrics(partyId: string) {
    const { data, error } = await api.GET('/providers/{party}/metrics', {
      params: { path: { party: partyId } },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** A provider's published reviews (P6-08) — only revealed, double-blind results; never pending. */
  async providerReviews(partyId: string) {
    const { data, error } = await api.GET('/providers/{party}/reviews', {
      params: { path: { party: partyId } },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * The caller's own provider profile (P1-08) — headline, verification tier, listed skills (with
   * their bilingual labels) and service areas. 404 until they've created one. `party_id` on the
   * response is the handle the public metrics/reviews endpoints take.
   */
  async providerProfile() {
    const { data, error } = await api.GET('/provider/profile');
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Register this install as a device (P1-04).
   *
   * Nothing in the app called this, so no `devices` row ever existed for a real user — and the
   * push rail (P5-05) sends to a party's non-revoked devices, which meant it had no recipients at
   * all. The push TOKEN still requires a native push plugin, which this build does not have; the
   * row, the platform and the app version are real either way, and the row is what a token later
   * attaches to.
   */
  async registerDevice(body: { platform: 'android' | 'ios' | 'web'; push_token?: string | null; app_version?: string }) {
    const { data, error } = await api.POST('/devices', {
      body,
      params: {
        header: {
          'Idempotency-Key': uuid(),
          // Required by the endpoint AND the id of the row, so it is passed explicitly rather than
          // left to the middleware, which only attaches it once it has been loaded.
          'X-Device-Id': await loadDeviceId(),
          'X-App-Version': environment.appVersion,
        },
      },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * End the session server-side (P1-03). Revokes the access token AND the refresh-token family —
   * without it, "log out" only forgot the tokens locally and left a 30-day refresh token valid on
   * the server, which is exactly the wrong outcome on a shared or lost phone.
   */
  async logout() {
    const { error } = await api.POST('/auth/logout', {
      params: { header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
  }

  /**
   * Mark an engagement finished (P7-02). Idempotent, and either party may do it.
   *
   * This is the event the rest of the lifecycle hangs off: it stamps `completed_at`, opens the
   * 14-day review window, schedules the review nudges and qualifies a referral. Nothing in the app
   * called it, so an engagement could be started and never finished — which also meant the review
   * flow below could never begin.
   */
  async completeEngagement(engagementId: string) {
    const { data, error } = await api.POST('/engagements/{engagement}/complete', {
      params: { path: { engagement: engagementId }, header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Review the other side of an engagement (P6-08).
   *
   * Double-blind: it rests hidden — even from an API peek — until both parties submit or the
   * window closes, and then both reveal at once. `private_note` is never published; it is for the
   * subject alone. One review per author per engagement, so a second attempt is a 409.
   */
  async submitReview(engagementId: string, body: { rating: number; body?: string; private_note?: string }) {
    const { data, error } = await api.POST('/engagements/{engagement}/reviews', {
      body,
      params: { path: { engagement: engagementId }, header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** The caller's consent state (P1-05) — the latest decision per purpose, keyed by purpose. */
  async consents() {
    const { data, error } = await api.GET('/consents');
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Grant or revoke one consent (P1-05). The locale the wording was READ IN is recorded with the
   * decision, which is the point of the endpoint: consent to a French policy is not consent to an
   * English one.
   */
  async recordConsent(purpose: 'terms' | 'privacy' | 'location_tracking' | 'id_verification' | 'marketing', granted: boolean, presentedLocale: 'fr' | 'en') {
    const { error } = await api.POST('/consents', {
      body: { purpose, granted, presented_locale: presentedLocale },
      params: { header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
  }

  /**
   * Raise a panic alert (P6-04).
   *
   * Everything that follows happens server-side — the emergency contacts are texted and staff are
   * alerted — precisely so it still works with the app backgrounded, or if the phone is taken. The
   * client's whole job is to make the call and say whether it landed.
   *
   * Coordinates are optional and travel together: a fix that never arrived is worse than none if it
   * sends someone to the wrong place, and waiting for one would delay the alert.
   */
  async raisePanic(body: { latitude?: number; longitude?: number; note?: string | null; assignment_id?: string | null }) {
    const { data, error } = await api.POST('/safety/panic', {
      body,
      params: { header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** Who the caller has blocked (P6-07), newest first, each labelled well enough to recognise. */
  async blocks() {
    const { data, error } = await api.GET('/blocks');
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Block a party (P6-07). Honoured in search, dispatch ranking and offer creation — and
   * BIDIRECTIONALLY, so the two are never matched again in either direction.
   */
  async blockParty(partyId: string) {
    const { data, error } = await api.POST('/blocks', {
      body: { blocked_party_id: partyId },
      params: { header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** Lift a block the caller placed (P6-07). */
  async unblockParty(partyId: string) {
    const { error } = await api.DELETE('/blocks/{party}', {
      params: { path: { party: partyId }, header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
  }

  /**
   * Report a party (P6-07). Queues a HUMAN review and never auto-penalises anyone — which is why
   * the app can promise a person will read it rather than implying an instant consequence.
   */
  async fileReport(body: {
    subject_party_id: string;
    category: 'fraud' | 'no_show' | 'harassment' | 'safety' | 'spam' | 'off_platform' | 'other';
    body: string;
    job_id?: string | null;
  }) {
    const { data, error } = await api.POST('/reports', {
      body,
      params: { header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** The caller's emergency contacts (P6-04) — who a panic alert reaches. */
  async emergencyContacts() {
    const { data, error } = await api.GET('/emergency-contacts');
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** Add an emergency contact (P6-04). */
  async addEmergencyContact(name: string, phoneE164: string) {
    const { data, error } = await api.POST('/emergency-contacts', {
      body: { name, phone_e164: phoneE164 },
      params: { header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** Remove an emergency contact (P6-04). */
  async removeEmergencyContact(contactId: string) {
    const { error } = await api.DELETE('/emergency-contacts/{contact}', {
      params: { path: { contact: contactId }, header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
  }

  /**
   * The quotations on a job (P2.5-01) — every submitted one for the customer, their own for a
   * provider.
   *
   * A quote arrives BEFORE any engagement, so there is no conversation for the server to narrate it
   * into; the job is the only place it can surface. Until this existed a provider could price a job
   * and the customer had no way to see it, which made the whole quote path a dead end.
   */
  async jobQuotations(jobId: string) {
    const { data, error } = await api.GET('/jobs/{job}/quotations', {
      params: { path: { job: jobId } },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Accept a quotation (P2.5-05) — the customer's side of the quote path.
   *
   * This is the moment the marketplace converts: it forms the engagement, generates the milestone
   * plan and captures the deposit into escrow. Exactly one engagement forms per job even under a
   * double tap, and a quote that has expired or been superseded comes back 409 rather than
   * quietly succeeding.
   */
  async acceptQuotation(quotationId: string) {
    const { data, error } = await api.POST('/quotations/{quotation}/accept', {
      params: { path: { quotation: quotationId }, header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Request a payout of the payable balance to a mobile-money wallet (P3-08).
   *
   * The pending payout RESERVES the funds; the ledger posts only when the gateway confirms, so a
   * request is a claim on the balance rather than a movement of it. Asking for more than the
   * unreserved balance is a 422 — which the caller shows in the server's own words, because
   * "insufficient" here has a precise meaning the client cannot restate.
   */
  async requestPayout(amountMinor: number, msisdn: string) {
    const { data, error } = await api.POST('/provider/payouts', {
      body: { amount_minor: amountMinor, msisdn },
      params: { header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  // GET /provider/credits is deliberately NOT called: /provider/earnings already returns
  // `lead_credits` in the same payload, and the earnings screen is the only place the balance is
  // shown. A second round trip for a number we already hold would be slower and no more true.

  /**
   * The caller's own verification documents and where each one stands (P6-01).
   *
   * Storage paths are never returned — the file itself is only ever reachable through a signed
   * short-TTL URL in the admin panel, and every staff view of one is written to the activity log
   * (P6-02). What comes back here is the status, not the document.
   */
  async verificationDocuments() {
    const { data, error } = await api.GET('/verification-documents');
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Upload an identity or licence document for human review (P6-01).
   *
   * Multipart, like the job report: the generated body type describes the FIELDS, and the serializer
   * passes the FormData through untouched so fetch sets the boundary itself. The tier is derived
   * server-side from `kind`, which is why the client sends a kind and never a tier — nobody can
   * self-assign tier 3 by mislabelling a selfie.
   */
  async submitVerificationDocument(kind: VerificationDocKind, file: File | Blob, expiresAt?: string) {
    const form = new FormData();
    form.set('kind', kind);
    form.set('file', file);
    if (expiresAt) {
      form.set('expires_at', expiresAt);
    }

    const { data, error } = await api.POST('/verification-documents', {
      params: { header: { 'Idempotency-Key': uuid() } },
      body: form as never,
      bodySerializer: (body: unknown) => body as FormData,
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Create (or update) the caller's provider profile — `CreateProviderProfile`, P1-08.
   *
   * Always allowed, by design (doc 10): anyone may declare themselves a provider; what they can
   * then DO is gated by facts, not by an approval. This is the call that makes the provider section
   * mean anything, and until now nothing in the app made it.
   */
  async createProviderProfile(body: { headline?: string; bio?: string; bio_language?: 'fr' | 'en' }) {
    const { data, error } = await api.POST('/provider/profile', {
      body,
      params: { header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * List a trade on the provider profile (P1-08). Requires a profile to exist — without one the
   * server answers 409 `precondition_unmet` rather than 403, because it is a missing fact and not a
   * refusal (P0-17).
   */
  async addProviderSkill(body: {
    skill_id: string;
    price_model: 'hourly' | 'fixed' | 'quote_only';
    rate_minor?: number;
    years_experience?: number;
  }) {
    const { error } = await api.POST('/provider/skills', {
      body,
      params: { header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
  }

  /**
   * Set the service area — a centre point and a radius (P1-08). Gated on location_tracking consent
   * (P1-05); without it the server answers `consent_required`.
   */
  async setServiceArea(body: { latitude: number; longitude: number; radius_m: number }) {
    const { error } = await api.POST('/provider/service-areas', {
      body,
      params: { header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
  }

  /** The provider's live incoming direct offers (P2-05/06), each with its PII-minimised job embedded. */
  async opportunities() {
    const { data, error } = await api.GET('/provider/opportunities');
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** The provider's active-work list (P5-03) — engagements still in flight, newest first. */
  async work() {
    const { data, error } = await api.GET('/provider/work');
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * One engagement's execution view (P5-03/04/06) — the exact site address plus THIS worker's
   * derived state (supports_check_in / checked_in / current_status / report_submitted), so the
   * work-detail screen renders only affordances the server would accept. 403 without an assignment.
   */
  async workDetail(engagementId: string) {
    const { data, error } = await api.GET('/provider/work/{engagement}', {
      params: { path: { engagement: engagementId } },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** Check in at the job site (P5-03) — opens a work session with geo, narrates `arrived`. */
  async checkIn(engagementId: string, latitude?: number, longitude?: number, accuracyM?: number) {
    const { data, error } = await api.POST('/engagements/{engagement}/check-in', {
      params: { path: { engagement: engagementId }, header: { 'Idempotency-Key': uuid() } },
      body: { latitude, longitude, accuracy_m: accuracyM },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** Check out (P5-03) — closes the open work session with the end geo. */
  async checkOut(engagementId: string, latitude?: number, longitude?: number, accuracyM?: number) {
    const { data, error } = await api.POST('/engagements/{engagement}/check-out', {
      params: { path: { engagement: engagementId }, header: { 'Idempotency-Key': uuid() } },
      body: { latitude, longitude, accuracy_m: accuracyM },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** Emit a structured status signal (P5-06) — narrated into the workspace timeline. */
  async recordStatus(engagementId: string, status: ProviderStatusSignal) {
    const { data, error } = await api.POST('/engagements/{engagement}/status', {
      params: { path: { engagement: engagementId }, header: { 'Idempotency-Key': uuid() } },
      body: { status },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Submit the on-site job report (P5-04) — summary, materials, extra charges and before/after
   * photos. Multipart, because photos ride along; the server strips every photo's EXIF.
   */
  async submitJobReport(engagementId: string, report: JobReportInput) {
    const form = new FormData();
    form.set('summary', report.summary);
    form.set('extra_charges_minor', String(report.extraChargesMinor ?? 0));
    (report.materials ?? []).forEach((m, i) => {
      form.set(`materials[${i}][label]`, m.label);
      form.set(`materials[${i}][qty]`, String(m.qty));
      form.set(`materials[${i}][unit_cost_minor]`, String(m.unitCostMinor));
    });
    (report.photos ?? []).forEach((p, i) => {
      form.set(`photos[${i}][file]`, p.file);
      form.set(`photos[${i}][kind]`, p.kind);
    });

    const { data, error } = await api.POST('/engagements/{engagement}/report', {
      params: { path: { engagement: engagementId }, header: { 'Idempotency-Key': uuid() } },
      // The generated body type describes the multipart FIELDS; the wire form is a FormData that
      // the serializer passes through untouched, so fetch sets the boundary itself.
      body: form as never,
      bodySerializer: (body: unknown) => body as FormData,
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Submit a deliverable (P4-08) — the REMOTE path's proof of work, the counterpart of the on-site
   * job report. The customer reviews it; an un-reviewed one auto-accepts after the window (P3-11).
   */
  async submitDeliverable(engagementId: string, title: string, mediaUrl?: string) {
    const { data, error } = await api.POST('/engagements/{engagement}/deliverables', {
      params: { path: { engagement: engagementId }, header: { 'Idempotency-Key': uuid() } },
      body: { title, media_url: mediaUrl ?? null },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Issue a warranty on a finished engagement (P6-11). Provider-only, one per engagement.
   *
   * This is the anti-leakage payoff: the warranty exists on-platform and nowhere else, so a job
   * taken off the platform to save a fee is a job with nothing standing behind it. Issuing it is
   * narrated into the thread, which is how the customer finds out it exists at all.
   */
  async issueWarranty(engagementId: string, durationDays: number, terms?: string) {
    const { data, error } = await api.POST('/engagements/{engagement}/warranty', {
      params: { path: { engagement: engagementId }, header: { 'Idempotency-Key': uuid() } },
      body: { duration_days: durationDays, terms: terms ?? null },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * File a warranty claim (P6-11). Customer-only, and only while the warranty is active — the
   * server 409s an expired or already-claimed one. A claim spawns a real remedy job, so the
   * description is what the returning provider will actually work from.
   */
  async fileWarrantyClaim(warrantyId: string, description: string) {
    const { data, error } = await api.POST('/warranties/{warranty}/claims', {
      params: { path: { warranty: warrantyId }, header: { 'Idempotency-Key': uuid() } },
      body: { description },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * The follow-ups waiting on this person (P7-02) — the nudges the server schedules when something
   * is owed: a quote about to expire, work waiting to be approved, a review not yet written.
   *
   * Both sides get them; which kinds you see depends on which side you are. The list is the same
   * one the SMS and push channels draw from, so what the app shows and what the phone buzzed about
   * cannot disagree.
   */
  async followUps() {
    const { data, error } = await api.GET('/follow-ups');
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Record what someone did about a follow-up (P7-02).
   *
   * This is not bookkeeping for its own sake: `response_action` is how a nudge's effectiveness is
   * measured, and how the ones that never earn a tap get killed rather than left to annoy people
   * forever. `dismissed` is as useful an answer as `opened`.
   */
  async respondToFollowUp(followUpId: string, action: FollowUpAction) {
    const { data, error } = await api.POST('/follow-ups/{followUp}/respond', {
      params: { path: { followUp: followUpId }, header: { 'Idempotency-Key': uuid() } },
      body: { response_action: action },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Raise a dispute on an engagement (P6-06). Open to EITHER party: the customer disputing the work
   * and the provider disputing the payment are the same mechanism, and a marketplace that only lets
   * the paying side complain is not neutral.
   *
   * The category routes it; the body is what a person on the team actually reads.
   */
  async raiseDispute(engagementId: string, category: DisputeCategory, body: string) {
    const { data, error } = await api.POST('/engagements/{engagement}/disputes', {
      params: { path: { engagement: engagementId }, header: { 'Idempotency-Key': uuid() } },
      body: { category, body },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** The disputes this party has raised (P6-06), newest first. */
  async disputes() {
    const { data, error } = await api.GET('/disputes');
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Refund what is still held in escrow (P3-14). Customer-only, and it ends the money side of the
   * engagement — the remaining balance goes back, and nothing further can be released from it.
   */
  async refundEngagement(engagementId: string, reason: string): Promise<void> {
    const { error } = await api.POST('/engagements/{engagement}/refund', {
      params: { path: { engagement: engagementId }, header: { 'Idempotency-Key': uuid() } },
      body: { reason },
    });
    if (error) {
      throw error;
    }
  }

  /**
   * Accept or reject a deliverable (P4-08). Customer-only, and only once — the server 409s a second
   * review, which is why the thread stops offering the buttons as soon as one lands.
   *
   * A rejection carries the customer's own words. The provider is going to redo work on the strength
   * of this sentence, so it is required rather than optional: "rejected" with no reason is an
   * instruction nobody can act on.
   */
  async reviewDeliverable(deliverableId: string, decision: 'accept' | 'reject', rejectReason?: string) {
    const { data, error } = await api.POST('/deliverables/{deliverable}/review', {
      params: { path: { deliverable: deliverableId }, header: { 'Idempotency-Key': uuid() } },
      body: { decision, reject_reason: rejectReason ?? null },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Mint a share link for an engagement (P6-05) — "someone is coming to my house, here is who and
   * when". Signed, expiring and revocable; the page it opens carries the provider's first name, the
   * status and the quarter, and never the street address.
   *
   * The raw token comes back exactly ONCE, in this response. It is not stored server-side in a form
   * that can be read back, so a link that is not kept here cannot be recovered — only revoked and
   * re-minted.
   */
  async createEngagementShare(engagementId: string) {
    const { data, error } = await api.POST('/engagements/{engagement}/share', {
      params: { path: { engagement: engagementId }, header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** Revoke a share link (P6-05). The page stops resolving immediately, before its expiry. */
  async revokeEngagementShare(shareId: string): Promise<void> {
    const { error } = await api.DELETE('/engagement-shares/{share}', {
      params: { path: { share: shareId }, header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
  }

  /**
   * Record work that was settled in CASH (P3-15). Provider-only: they are the one who was handed
   * the money, and self-reporting is strictly in their interest — it is what turns an off-platform
   * job into on-platform history and reputation. The platform books its commission from it.
   *
   * `milestoneId` attaches the settlement to one slice of the work; omitted, it settles against the
   * engagement as a whole.
   */
  async recordCashSettlement(engagementId: string, amountMinor: number, milestoneId?: string) {
    const { data, error } = await api.POST('/engagements/{engagement}/cash-settlements', {
      params: { path: { engagement: engagementId }, header: { 'Idempotency-Key': uuid() } },
      body: { amount_minor: amountMinor, milestone_id: milestoneId ?? null },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Submit a priced quotation for a job (P2.5-01). Only `open`/`offered` jobs accept one (409
   * otherwise). The subtotal is computed server-side from the lines and the terms freeze on submit —
   * a revision is a NEW version, never an in-place edit.
   */
  async submitQuotation(jobId: string, quote: QuotationInput) {
    const { data, error } = await api.POST('/jobs/{job}/quotations', {
      params: { path: { job: jobId }, header: { 'Idempotency-Key': uuid() } },
      body: {
        lines: quote.lines.map((l) => ({
          kind: l.kind,
          label: l.label,
          quantity: l.quantity,
          unit_price_minor: l.unitPriceMinor,
        })),
        deposit_minor: quote.depositMinor ?? 0,
        notes: quote.notes ?? null,
        valid_until: quote.validUntil,
      },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Revise a submitted quotation (P2.5-01) — a NEW version that supersedes the old one, never an
   * in-place edit (doc 06 / rule #9). The customer keeps a readable history of what was offered
   * when, and a price cannot change under someone who is still deciding.
   *
   * Only a `submitted` quote can be revised; the server 409s an accepted or expired one.
   */
  async reviseQuotation(quotationId: string, quote: QuotationInput) {
    const { data, error } = await api.POST('/quotations/{quotation}/revise', {
      params: { path: { quotation: quotationId }, header: { 'Idempotency-Key': uuid() } },
      body: {
        lines: quote.lines.map((l) => ({
          kind: l.kind,
          label: l.label,
          quantity: l.quantity,
          unit_price_minor: l.unitPriceMinor,
        })),
        deposit_minor: quote.depositMinor ?? 0,
        notes: quote.notes ?? null,
        valid_until: quote.validUntil,
      },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** Accept a direct offer → forms the engagement (P2-06). Provider-gated; may 409 on a fact gate. */
  async acceptOffer(offerId: string) {
    const { data, error } = await api.POST('/offers/{offer}/accept', {
      params: { path: { offer: offerId }, header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** The provider's earnings summary (P3-07/08) — payable balance, reserved payouts, credits, history. */
  async earnings() {
    const { data, error } = await api.GET('/provider/earnings');
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * The provider's customer book (P7-08) — every customer they have engaged, with job count,
   * completions, lifetime value, last engagement and do-not-contact status. Server-ordered by most
   * recently engaged.
   */
  async providerCustomers() {
    const { data, error } = await api.GET('/provider/customers');
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * The provider's work funnel (P7-08) — offers awaiting an answer, quotes out with the customer,
   * work in flight, work completed in the window. Counts and values only; not a weighted forecast.
   */
  async providerPipeline() {
    const { data, error } = await api.GET('/provider/pipeline');
    if (error) {
      throw error;
    }
    return data.data;
  }

  /**
   * Schedule a manual re-engagement nudge for one customer (P7-08). Rides the SAME budget and
   * consent gates as an automated follow-up, so a 422 here is the platform refusing to let a
   * provider over-contact someone — the `detail` is worth showing verbatim.
   */
  async scheduleManualFollowUp(partyId: string) {
    const { data, error } = await api.POST('/provider/customers/{party}/follow-up', {
      params: { path: { party: partyId }, header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** Mark a customer do-not-contact (P7-08). Honoured absolutely — at schedule time and at dispatch. */
  async setDoNotContact(partyId: string): Promise<void> {
    const { error } = await api.POST('/provider/customers/{party}/do-not-contact', {
      params: { path: { party: partyId }, header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
  }

  /** Lift a do-not-contact (P7-08). */
  async removeDoNotContact(partyId: string): Promise<void> {
    const { error } = await api.DELETE('/provider/customers/{party}/do-not-contact', {
      params: { path: { party: partyId }, header: { 'Idempotency-Key': uuid() } },
    });
    if (error) {
      throw error;
    }
  }

  /** Send a direct offer to a provider for one of the caller's jobs (P2-05). Owner-gated, idempotent. */
  async createDirectOffer(jobId: string, providerPartyId: string, message?: string) {
    const { data, error } = await api.POST('/jobs/{job}/offers', {
      params: { path: { job: jobId }, header: { 'Idempotency-Key': uuid() } },
      body: { provider_party_id: providerPartyId, message: message ?? null },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }
}
