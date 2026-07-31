import type { CapacitorConfig } from '@capacitor/cli';

/**
 * Native dev builds talk to a plain-HTTP API on the developer's machine (`10.0.2.2:8100` from the
 * Android emulator). Android 9+ blocks cleartext traffic by default, and Capacitor's generated
 * network-security config only permits `localhost` — so without this the packaged app cannot reach
 * the dev server at all. Worse, it fails *quietly*: `AuthService` treats an unreachable backend as
 * "offline" and walks on to the next screen, so the app looks like it works while talking to
 * nothing. (Observed on an emulator: "Cleartext HTTP traffic to 10.0.2.2 not permitted".)
 *
 * It is opt-in via an env var rather than simply switched on, because this flag ships in the
 * manifest: a production build must never permit cleartext, where the API is HTTPS and downgrading
 * it would be a real weakening. Set `HM_NATIVE_DEV=1` before `npx cap sync android`.
 */
const nativeDev = process.env['HM_NATIVE_DEV'] === '1';

const config: CapacitorConfig = {
  appId: 'cm.handyman.app',
  appName: 'HandyMan',
  webDir: 'www',
  ...(nativeDev ? { server: { cleartext: true } } : {}),
  plugins: {
    /**
     * Route `fetch` through the native HTTP stack on device.
     *
     * The app is served from `https://localhost` in a packaged build, so a call to any `http://`
     * API is MIXED CONTENT and the WebView refuses it — independently of Android's cleartext
     * policy. Observed on an emulator: `CapacitorHttp` reached the API while a plain `fetch` threw
     * "Failed to fetch". Native transport also skips CORS entirely, so the preflight round trip
     * before every request disappears — which matters on the connections this product targets.
     *
     * This has teeth: multipart uploads (voice notes, P4-05; report photos, P5-04) and binary
     * responses (the authorized media route) go through a different implementation on device than
     * in the browser, so those paths are the ones to re-check after any Capacitor upgrade.
     */
    CapacitorHttp: { enabled: true },
  },
};

export default config;
