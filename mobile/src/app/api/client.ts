import { Capacitor } from '@capacitor/core';
import createClient, { type Middleware } from 'openapi-fetch';
import { environment } from '../../environments/environment';
import type { paths } from './generated/schema';
import { tokenStore } from './token-store';

/**
 * Where the API lives, for THIS target (P5-01). A browser build is served from the same host as the
 * API and uses a relative path; a packaged app's origin is the device, so a relative path would
 * resolve to `capacitor://localhost` and never leave the phone — native must use the absolute URL.
 */
export const apiBaseUrl = Capacitor.isNativePlatform()
  ? environment.nativeApiBaseUrl
  : environment.apiBaseUrl;

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

/**
 * Hand a `Request` to the platform's `fetch` — unwrapped first, on native.
 *
 * Capacitor routes `fetch` through the OS HTTP stack (enabled app-wide so the WebView can reach the
 * API at all), and its patched implementation reads the **body** from the second argument only.
 * Called the way openapi-fetch calls it — `fetch(request)`, one `Request` object, no init — the body
 * is silently dropped. Headers survive; the body does not.
 *
 * The effect on device was total: every write the packaged app made arrived at the server empty. An
 * OTP request reached `/auth/otp/request` carrying no phone number and came back 422 "the phone
 * e164 field is required", which reads like a client validation bug and is nothing of the kind. It
 * is invisible in a browser, where `fetch` is the real one and a `Request` is a `Request`.
 *
 * So on native we take the Request apart and pass `(url, init)`, which is the shape the patch reads.
 */
async function sendRequest(request: Request): Promise<Response> {
  if (!Capacitor.isNativePlatform()) {
    return globalThis.fetch(request);
  }

  const init: RequestInit = { method: request.method, headers: request.headers };

  if (request.method !== 'GET' && request.method !== 'HEAD') {
    const contentType = request.headers.get('Content-Type') ?? '';
    if (contentType.startsWith('multipart/form-data')) {
      // Re-send as FormData, and drop the serialized Content-Type: its `boundary=` describes the
      // body we just consumed, and re-encoding produces a different one. Letting the transport set
      // the header keeps the boundary and the bytes in agreement.
      const headers = new Headers(request.headers);
      headers.delete('Content-Type');
      init.headers = headers;
      init.body = await request.formData();
    } else {
      const text = await request.text();
      if (text !== '') {
        init.body = text;
      }
    }
  }

  return normalizeNativeResponse(await globalThis.fetch(request.url, init));
}

/**
 * Repair the response the native HTTP stack synthesizes.
 *
 * It reports `Content-Length: 0` on every response, whatever the body actually is — a 679-byte
 * provider list comes back claiming to be empty. openapi-fetch reads that header to decide whether
 * there is anything to parse (`status === 204 || Content-Length === "0"` → no data), so on device
 * EVERY successful read resolved to `data: undefined`. Callers then threw on `data.data`, the
 * offline cache caught the throw as "could not refresh", and the screen quietly showed its fixture.
 * The app looked like it was working and was showing demo data on every surface.
 *
 * Reading the bytes back gives the real length. `arrayBuffer` rather than `text` so this stays
 * correct if a binary response ever comes through here, and a genuinely empty body still measures 0
 * — a real 204 keeps meaning what it means.
 */
async function normalizeNativeResponse(response: Response): Promise<Response> {
  const body = await response.arrayBuffer();
  const headers = new Headers(response.headers);
  headers.set('Content-Length', String(body.byteLength));

  return new Response(body.byteLength === 0 ? null : body, {
    status: response.status,
    statusText: response.statusText,
    headers,
  });
}

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
    // Through the same unwrapping as the first attempt — a replay that lost its body would turn a
    // recoverable 401 into a silent 422.
    return sendRequest(original);
  },
};

export const api = createClient<paths>({
  baseUrl: apiBaseUrl,
  headers: { Accept: 'application/json' },
  // Resolve `fetch` per request rather than letting openapi-fetch capture `globalThis.fetch` when
  // this module is evaluated. The behaviour is identical at runtime, and it means the transport can
  // actually be stood in for — a test that swaps `window.fetch` after import (which is the only
  // time it can) was otherwise silently talking to the real network.
  fetch: (request) => sendRequest(request),
});

api.use(authMiddleware);

export type { paths, components, operations } from './generated/schema';
