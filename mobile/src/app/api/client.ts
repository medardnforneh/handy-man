import createClient, { type Middleware } from 'openapi-fetch';
import { environment } from '../../environments/environment';
import type { paths } from './generated/schema';
import { tokenStore } from './token-store';

/**
 * The typed API client for /api/v1 (build plan P0-10). Every request/response is typed against the
 * OpenAPI contract in openapi/openapi.yaml via the GENERATED `paths` — a client model is never
 * hand-written (CLAUDE.md / doc 08). Regenerate types with `npm run api:generate`.
 *
 * The base URL comes from the environment (dev → the local Laravel API on :8000; prod → same-origin
 * /api/v1). A request middleware attaches the Bearer access token (P1-03) and the app version header
 * (the force-update gate, P0-08); the Idempotency-Key for unsafe writes is added per-call by the
 * action that needs it.
 */
// A clone of each in-flight request (body intact), kept by openapi-fetch's per-request id so a 401
// can be retried after refreshing — the original request's body is consumed once it is sent.
const inFlight = new Map<string, Request>();

const authMiddleware: Middleware = {
  onRequest({ request, id }) {
    const token = tokenStore.get();
    if (token !== null) {
      request.headers.set('Authorization', `Bearer ${token}`);
    }
    request.headers.set('X-App-Version', environment.appVersion);
    inFlight.set(id, request.clone());
    return request;
  },

  // On a 401, rotate the access token (P1-03 refresh) once and replay the original request. The
  // refresh/otp endpoints are exempt so a failing refresh or verify can't loop.
  async onResponse({ request, response, id }) {
    const original = inFlight.get(id);
    inFlight.delete(id);
    const isAuthEntry = request.url.includes('/auth/refresh') || request.url.includes('/auth/otp');
    if (response.status !== 401 || original === undefined || isAuthEntry) {
      return undefined;
    }
    const fresh = await tokenStore.refresh();
    if (fresh === null) {
      return undefined; // no session / refresh failed — let the 401 stand
    }
    original.headers.set('Authorization', `Bearer ${fresh}`);
    return fetch(original);
  },
};

export const api = createClient<paths>({
  baseUrl: environment.apiBaseUrl,
  headers: { Accept: 'application/json' },
  // Resolve `fetch` per request rather than letting openapi-fetch capture `globalThis.fetch` when
  // this module is evaluated. The behaviour is identical at runtime, and it means the transport can
  // actually be stood in for — a test that swaps `window.fetch` after import (which is the only
  // time it can) was otherwise silently talking to the real network.
  fetch: (request) => globalThis.fetch(request),
});

api.use(authMiddleware);

export type { paths, components, operations } from './generated/schema';
