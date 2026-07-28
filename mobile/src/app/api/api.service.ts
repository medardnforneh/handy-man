import { Injectable } from '@angular/core';
import { api } from './client';

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

  /** The signed-in user (P1-03) — requires the Bearer. Drives the account/profile identity. */
  async me() {
    const { data, error } = await api.GET('/auth/me');
    if (error) {
      throw error;
    }
    return data.data;
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
    return data.data;
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
}
