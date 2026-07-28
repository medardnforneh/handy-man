export const environment = {
  production: true,
  // Prod: the app is served behind the same domain as the API (reverse-proxied to /api/v1).
  apiBaseUrl: '/api/v1',
  appVersion: '1.0.0',
  // Reverb behind the same domain over TLS. The key is a PUBLIC client identifier (never the
  // secret) — it only names the app; who may subscribe is decided by the auth endpoint.
  reverb: {
    key: 'bwnksdabq0wufulekwsw',
    host: window.location.hostname,
    port: 443,
    scheme: 'https' as 'http' | 'https',
  },
};
