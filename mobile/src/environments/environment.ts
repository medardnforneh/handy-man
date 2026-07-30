// This file can be replaced during build by using the `fileReplacements` array.
// `ng build` replaces `environment.ts` with `environment.prod.ts`.
// The list of file replacements can be found in `angular.json`.

export const environment = {
  production: false,
  // Dev: the app (served on another port) talks to the Laravel API on :8100 — 8000 is taken on this
  // machine. Start it with `php artisan serve --port=8100` from backend/.
  apiBaseUrl: 'http://127.0.0.1:8100/api/v1',
  // A packaged app can't use a relative URL (its origin is the device), and from the Android
  // emulator the host machine is 10.0.2.2 — `127.0.0.1` there means the emulator itself.
  nativeApiBaseUrl: 'http://10.0.2.2:8100/api/v1',
  appVersion: '1.0.0',
  // Reverb (P4-03/04). Must match backend/.env REVERB_APP_KEY / REVERB_PORT — start it with
  // `php artisan reverb:start` from backend/.
  // `scheme` is deliberately the full union, not a literal: narrowing it to 'http' would make the
  // `scheme === 'https'` TLS check a compile error in this configuration only.
  reverb: {
    key: 'bwnksdabq0wufulekwsw',
    host: '127.0.0.1',
    nativeHost: '10.0.2.2',
    port: 8080,
    scheme: 'http' as 'http' | 'https',
  },
};

/*
 * For easier debugging in development mode, you can import the following file
 * to ignore zone related error stack frames such as `zone.run`, `zoneDelegate.invokeTask`.
 *
 * This import should be commented out in production mode because it will have a negative impact
 * on performance if an error is thrown.
 */
// import 'zone.js/plugins/zone-error';  // Included with Angular CLI.
