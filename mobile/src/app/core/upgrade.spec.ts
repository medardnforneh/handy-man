import { api } from '../api/client';
import { markUpgradeRequired, upgradeRequired } from './upgrade';

/**
 * The force-update kill switch, client side (P0-08, launch checklist doc 05). The server's side —
 * a build below the minimum gets 426 on every request — is proven in the backend suite; this is the
 * half that was missing: the app has to NOTICE, or a retired build just looks broken.
 */
describe('force-update kill switch (426 Upgrade Required)', () => {
  beforeEach(() => {
    upgradeRequired.set(null);
  });

  it('starts with nothing required', () => {
    expect(upgradeRequired()).toBeNull();
  });

  it('records the server minimum from the first 426 the transport sees', async () => {
    spyOn(window, 'fetch').and.resolveTo(
      new Response(JSON.stringify({ type: 'https://errors.handyman.cm/upgrade-required', status: 426, min_app_version: '2.3.0' }), {
        status: 426,
        headers: { 'Content-Type': 'application/problem+json' },
      }),
    );

    await api.GET('/meta');

    expect(upgradeRequired()).toEqual({ minVersion: '2.3.0' });
  });

  it('is one fact, not one per refused request', () => {
    markUpgradeRequired('2.3.0');
    markUpgradeRequired('9.9.9');

    expect(upgradeRequired()?.minVersion).toBe('2.3.0');
  });

  it('lets a later refusal fill in a minimum the first one could not carry', () => {
    markUpgradeRequired(null);
    markUpgradeRequired('2.3.0');
    markUpgradeRequired(null);

    expect(upgradeRequired()?.minVersion).toBe('2.3.0');
  });

  it('still trips on a 426 with no body', async () => {
    spyOn(window, 'fetch').and.resolveTo(new Response(null, { status: 426 }));

    await api.GET('/meta');

    expect(upgradeRequired()).toEqual({ minVersion: null });
  });
});
