/**
 * The deployed origin of the API, used ONLY by packaged native builds (P5-01).
 *
 * A PWA is served from the same host as the API, so it can use a relative `/api/v1`. An installed
 * Android/iOS app is not: its origin is `capacitor://localhost`, so a relative URL resolves to the
 * device itself and every request fails. Native therefore needs an absolute URL, and there is no way
 * to derive it — it has to be stated.
 *
 * **This is a placeholder.** The hosting ADR (docs/adr/0001-hosting-region.md) chose in-country
 * hosting but no domain is registered yet; set this to the real origin before the first store build.
 */
const NATIVE_API_ORIGIN = 'https://app.handyman.cm';

export const environment = {
  production: true,
  // Prod: the app is served behind the same domain as the API (reverse-proxied to /api/v1).
  apiBaseUrl: '/api/v1',
  nativeApiBaseUrl: `${NATIVE_API_ORIGIN}/api/v1`,
  appVersion: '1.0.0',
  // Reverb behind the same domain over TLS. The key is a PUBLIC client identifier (never the
  // secret) — it only names the app; who may subscribe is decided by the auth endpoint.
  reverb: {
    key: 'bwnksdabq0wufulekwsw',
    host: window.location.hostname,
    // Same reasoning as the API URL: on native `window.location.hostname` is `localhost`, which is
    // the device, so the socket host must be the deployed one.
    nativeHost: new URL(NATIVE_API_ORIGIN).hostname,
    port: 443,
    scheme: 'https' as 'http' | 'https',
  },
};
