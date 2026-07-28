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
}
