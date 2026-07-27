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
const authMiddleware: Middleware = {
  onRequest({ request }) {
    const token = tokenStore.get();
    if (token !== null) {
      request.headers.set('Authorization', `Bearer ${token}`);
    }
    request.headers.set('X-App-Version', environment.appVersion);
    return request;
  },
};

export const api = createClient<paths>({
  baseUrl: environment.apiBaseUrl,
  headers: { Accept: 'application/json' },
});

api.use(authMiddleware);

export type { paths, components, operations } from './generated/schema';
