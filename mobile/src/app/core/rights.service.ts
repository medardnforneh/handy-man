import { Injectable, inject } from '@angular/core';
import { Capacitor } from '@capacitor/core';
import { ApiService } from '../api/api.service';

/** How the copy was delivered, so the screen can say what actually happened rather than guess. */
export type DeliveryResult = 'saved' | 'copied' | 'failed';

/**
 * The data-subject rights (P1-10): see everything held about you, and have it destroyed.
 *
 * Outside both sections, like safety — these belong to the person, not to a role, and a provider
 * has exactly the same rights as a customer.
 */
@Injectable({ providedIn: 'root' })
export class RightsService {
  private readonly api = inject(ApiService);

  /** Everything the platform holds about the caller, or null if it could not be fetched. */
  async export(): Promise<Record<string, unknown> | null> {
    try {
      return (await this.api.dataExport()) as Record<string, unknown>;
    } catch {
      return null;
    }
  }

  /**
   * Hand the export to the person as something they keep.
   *
   * Two mechanisms, because only one works in each place and neither works in both. A browser
   * saves a file from an object URL; a Capacitor WebView silently swallows that same click, and a
   * button that appears to save a file and does nothing is the failure this codebase keeps finding.
   * Saving to the device filesystem there would need a native plugin the app does not carry yet, so
   * native copies to the clipboard instead — a real action, and the screen names which one it did.
   */
  async deliver(payload: Record<string, unknown>): Promise<DeliveryResult> {
    const json = JSON.stringify(payload, null, 2);

    if (Capacitor.isNativePlatform()) {
      try {
        await navigator.clipboard.writeText(json);
        return 'copied';
      } catch {
        return 'failed';
      }
    }

    try {
      const url = URL.createObjectURL(new Blob([json], { type: 'application/json' }));
      const a = document.createElement('a');
      a.href = url;
      a.download = `handyman-data-${new Date().toISOString().slice(0, 10)}.json`;
      a.click();
      // Revoked on the next tick: revoking synchronously can beat the download starting.
      setTimeout(() => URL.revokeObjectURL(url), 0);
      return 'saved';
    } catch {
      return 'failed';
    }
  }

  /**
   * Erase the account (P1-10 crypto-shred). Irreversible, and the server offers no way back — the
   * data key is destroyed, not archived.
   */
  async erase(): Promise<boolean> {
    try {
      await this.api.eraseAccount();
      return true;
    } catch {
      return false;
    }
  }
}
