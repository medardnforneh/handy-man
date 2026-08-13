/** A GPS fix, in the shape the API's geo bodies take. */
export interface GeoFix {
  latitude: number;
  longitude: number;
  accuracyM?: number;
}

/**
 * A best-effort GPS fix.
 *
 * Deliberately forgiving: a short timeout and a null result on refusal or failure. Every caller has
 * a reasonable thing to do without one — a worker with a dead GPS must still be able to check in
 * (the server accepts a session with no coordinates), and a screen that needs a real point can say
 * so rather than hang.
 *
 * Lives here rather than inside a feature service because three unrelated flows need it: check-in
 * and check-out (P5-03), the provider's service area (P1-08) and saving an address (P1-06).
 */
export function currentPosition(): Promise<GeoFix | null> {
  if (typeof navigator === 'undefined' || !navigator.geolocation) {
    return Promise.resolve(null);
  }
  return new Promise((resolve) => {
    navigator.geolocation.getCurrentPosition(
      (pos) => resolve({
        latitude: pos.coords.latitude,
        longitude: pos.coords.longitude,
        accuracyM: pos.coords.accuracy ?? undefined,
      }),
      () => resolve(null),
      { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 },
    );
  });
}
