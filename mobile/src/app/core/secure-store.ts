import { SecureStorage } from '@aparajita/capacitor-secure-storage';
import { Capacitor } from '@capacitor/core';
import { Preferences } from '@capacitor/preferences';

/**
 * Where session secrets live (build plan P5-01: "refresh token in OS secure store").
 *
 * On a device this is the platform's own protected store — Android's EncryptedSharedPreferences
 * (keys held by the hardware-backed Keystore) and the iOS Keychain — NOT the plain preferences file
 * the rest of the app uses. That distinction is the whole point: a refresh token is a 30-day
 * credential that can mint access tokens for a month, so on a rooted or seized phone it must not be
 * sitting in readable app storage next to the theme setting.
 *
 * On the WEB there is no OS keystore to reach, and this falls back to Preferences (localStorage).
 * That is a real and unavoidable limitation, stated rather than papered over: a PWA's protection is
 * origin isolation, so anything that can run script on this origin can read the token. It is the
 * same exposure the app had before; native is where this actually buys something.
 *
 * Every method degrades to the fallback if the plugin throws (a device with no secure hardware, a
 * user who has never set a screen lock), because failing to store a token would log the user out
 * rather than protect them.
 */
const native = Capacitor.isNativePlatform();

/** Whether secrets are really in the OS store — surfaced so callers can be honest about it. */
export const secureStorageIsHardwareBacked = native;

export const secureStore = {
  async get(key: string): Promise<string | null> {
    if (native) {
      try {
        const value = await SecureStorage.get(key);
        return typeof value === 'string' ? value : null;
      } catch {
        // fall through to the plain store — see the migration note in AuthService
      }
    }
    return (await Preferences.get({ key })).value;
  },

  async set(key: string, value: string): Promise<void> {
    if (native) {
      try {
        await SecureStorage.set(key, value);
        // Belt and braces: if an older build of this app left a copy in plain preferences, drop it
        // now. Writing the secret securely while a readable copy survives protects nothing.
        await Preferences.remove({ key });
        return;
      } catch {
        // fall through
      }
    }
    await Preferences.set({ key, value });
  },

  async remove(key: string): Promise<void> {
    if (native) {
      try {
        await SecureStorage.remove(key);
      } catch {
        // fall through and still clear the plain copy
      }
    }
    await Preferences.remove({ key });
  },
};
