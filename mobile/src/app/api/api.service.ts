import { Injectable } from '@angular/core';
import { environment } from '../../environments/environment';
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
      params: { header: { 'Idempotency-Key': crypto.randomUUID() } },
      body: { locale },
    });
    if (error) {
      throw error;
    }
    return data.data;
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
      params: { path: { job: jobId }, header: { 'Idempotency-Key': crypto.randomUUID() } },
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
      params: { path: { milestone: milestoneId }, header: { 'Idempotency-Key': crypto.randomUUID() } },
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
      params: { header: { 'Idempotency-Key': crypto.randomUUID() } },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }

  /** Publish a draft job (draft → open) so providers can be found (P2-03). */
  async publishJob(id: string): Promise<void> {
    const { error } = await api.POST('/jobs/{job}/publish', {
      params: { path: { job: id }, header: { 'Idempotency-Key': crypto.randomUUID() } },
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
      params: { path: { conversation: conversationId }, header: { 'Idempotency-Key': crypto.randomUUID() } },
    });
    if (error) {
      throw error;
    }
  }

  /** Post a free-form text message to the thread (P4-02). Structured kinds are rejected server-side. */
  async postMessage(jobId: string, body: string) {
    const { data, error } = await api.POST('/jobs/{job}/messages', {
      params: { path: { job: jobId }, header: { 'Idempotency-Key': crypto.randomUUID() } },
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
      params: { path: { engagement: engagementId }, header: { 'Idempotency-Key': crypto.randomUUID() } },
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
      params: { path: { engagement: engagementId }, header: { 'Idempotency-Key': crypto.randomUUID() } },
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
      params: { path: { engagement: engagementId }, header: { 'Idempotency-Key': crypto.randomUUID() } },
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
      params: { path: { engagement: engagementId }, header: { 'Idempotency-Key': crypto.randomUUID() } },
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
      params: { path: { engagement: engagementId }, header: { 'Idempotency-Key': crypto.randomUUID() } },
      body: { title, media_url: mediaUrl ?? null },
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
      params: { path: { job: jobId }, header: { 'Idempotency-Key': crypto.randomUUID() } },
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
      params: { path: { offer: offerId }, header: { 'Idempotency-Key': crypto.randomUUID() } },
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

  /** Send a direct offer to a provider for one of the caller's jobs (P2-05). Owner-gated, idempotent. */
  async createDirectOffer(jobId: string, providerPartyId: string, message?: string) {
    const { data, error } = await api.POST('/jobs/{job}/offers', {
      params: { path: { job: jobId }, header: { 'Idempotency-Key': crypto.randomUUID() } },
      body: { provider_party_id: providerPartyId, message: message ?? null },
    });
    if (error) {
      throw error;
    }
    return data.data;
  }
}
