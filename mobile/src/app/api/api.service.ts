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

  /** Public bilingual skills taxonomy (P1-07) — the real source behind the category rail. */
  async skills() {
    const { data, error } = await api.GET('/skills');
    if (error) {
      throw error;
    }
    return data;
  }
}
