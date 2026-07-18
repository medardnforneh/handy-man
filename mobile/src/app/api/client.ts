import createClient from 'openapi-fetch';
import type { paths } from './generated/schema';

/**
 * The typed API client for /api/v1 (build plan P0-10). Every request/response is typed against
 * the OpenAPI contract in openapi/openapi.yaml via the GENERATED `paths` — a client model is
 * never hand-written (CLAUDE.md / doc 08). Regenerate types with `npm run api:generate`.
 *
 * Auth (Bearer), Idempotency-Key and X-App-Version headers are attached by the app's HTTP layer
 * (P1-03 / P5-01); this module only fixes the base URL and the typed surface.
 */
export const api = createClient<paths>({
  baseUrl: '/api/v1',
  headers: { Accept: 'application/json' },
});

export type { paths, components, operations } from './generated/schema';
