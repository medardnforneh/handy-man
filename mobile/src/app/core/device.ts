import { Capacitor } from '@capacitor/core';
import { Preferences } from '@capacitor/preferences';
import { uuid } from './uuid';

const DEVICE_KEY = 'device_id';

/** Cached so the header middleware, which is synchronous, can read it without awaiting storage. */
let deviceId: string | null = null;

/**
 * This installation's stable id.
 *
 * The API takes it as `X-Device-Id` and it is the primary key of the `devices` row (P1-04) — so the
 * same install keeps one row across logins rather than accumulating one per session. It is also the
 * key the OTP limiter counts against (5 requests per hour per device, P1-02): with no header the
 * app was never sending one, so that third limit — the one that catches a single handset working
 * through a list of numbers, which the phone and IP limits do not — has been inert since it shipped.
 *
 * A random UUID, not a fingerprint: it identifies an installation, not a person, and it disappears
 * when the app is uninstalled.
 */
export async function loadDeviceId(): Promise<string> {
  if (deviceId !== null) {
    return deviceId;
  }
  const stored = (await Preferences.get({ key: DEVICE_KEY })).value;
  if (stored) {
    deviceId = stored;
    return stored;
  }
  const minted = uuid();
  await Preferences.set({ key: DEVICE_KEY, value: minted });
  deviceId = minted;
  return minted;
}

/** The id if it has been loaded, else null — for the synchronous request middleware. */
export function currentDeviceId(): string | null {
  return deviceId;
}

/** What the API's `platform` enum calls this build. */
export function devicePlatform(): 'android' | 'ios' | 'web' {
  const platform = Capacitor.getPlatform();
  return platform === 'android' || platform === 'ios' ? platform : 'web';
}
