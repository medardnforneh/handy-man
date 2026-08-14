import { Injectable, inject } from '@angular/core';
import { ApiService } from '../api/api.service';
import { currentPosition } from './geolocation';

/** One emergency contact, as the safety screen renders it. */
export interface EmergencyContact {
  id: string;
  name: string;
  phone: string;
}

/** What a raise actually achieved, so the screen can say it without overstating. */
export interface PanicResult {
  ok: boolean;
  /** True when a real fix was attached. A raise without one still reaches people. */
  located: boolean;
  detail?: string;
}

/** Someone the caller has blocked, with enough of a label to recognise them by. */
export interface BlockedParty {
  partyId: string;
  label: string;
}

/** The reasons a report can be filed under (P6-07). `off_platform` is first-class, not "other". */
export type ReportCategory =
  | 'fraud' | 'no_show' | 'harassment' | 'safety' | 'spam' | 'off_platform' | 'other';

/**
 * Safety (P6-04, P6-07): the panic alert, the people it reaches, and the boundaries you can put
 * between yourself and someone else.
 *
 * This belongs to the person rather than to either role — a customer letting a stranger into their
 * home and a worker walking into an unknown site need exactly the same thing — so it sits in core
 * and both sections link to the one screen.
 *
 * Nothing here is queued for offline. An alert that arrives when the network comes back is not a
 * safety feature, and pretending otherwise is worse than saying plainly that it did not send.
 */
@Injectable({ providedIn: 'root' })
export class SafetyService {
  private readonly api = inject(ApiService);

  async contacts(): Promise<EmergencyContact[] | null> {
    try {
      const rows = await this.api.emergencyContacts();
      return rows.map((c) => ({ id: c.id, name: c.name, phone: c.phone_e164 }));
    } catch {
      return null;
    }
  }

  async addContact(name: string, phone: string): Promise<boolean> {
    try {
      await this.api.addEmergencyContact(name, phone);
      return true;
    } catch {
      return false;
    }
  }

  async removeContact(id: string): Promise<boolean> {
    try {
      await this.api.removeEmergencyContact(id);
      return true;
    } catch {
      return false;
    }
  }

  // --- blocks and reports (P6-07) -----------------------------------------------------------------

  async blocks(): Promise<BlockedParty[] | null> {
    try {
      const rows = await this.api.blocks();
      return rows.map((b) => ({
        partyId: b.blocked_party_id,
        // A label the server could not resolve is still a real block, so it is listed either way.
        label: b.blocked_label ?? '',
      }));
    } catch {
      return null;
    }
  }

  async block(partyId: string): Promise<boolean> {
    try {
      await this.api.blockParty(partyId);
      return true;
    } catch {
      return false;
    }
  }

  async unblock(partyId: string): Promise<boolean> {
    try {
      await this.api.unblockParty(partyId);
      return true;
    } catch {
      return false;
    }
  }

  /**
   * File a report about someone (P6-07).
   *
   * It queues a human review and never penalises anyone automatically, which is exactly what the
   * UI should say: a promise that somebody will read it, not a threat of an instant consequence
   * the platform does not actually deliver.
   */
  async report(subjectPartyId: string, category: ReportCategory, body: string, jobId?: string): Promise<boolean> {
    try {
      await this.api.fileReport({
        subject_party_id: subjectPartyId,
        category,
        body: body.trim(),
        job_id: jobId ?? null,
      });
      return true;
    } catch {
      return false;
    }
  }

  /**
   * Raise the alarm.
   *
   * The location attempt is bounded and never blocking: if a fix does not arrive quickly the alert
   * goes without one, because a late alert is the failure mode that matters here. Everything the
   * alert then does — texting the contacts, waking staff — happens on the server, so it survives
   * the app being backgrounded or the phone being taken.
   */
  async panic(note?: string): Promise<PanicResult> {
    const fix = await Promise.race([
      currentPosition(),
      new Promise<null>((resolve) => setTimeout(() => resolve(null), 4000)),
    ]);

    try {
      await this.api.raisePanic({
        latitude: fix?.latitude,
        longitude: fix?.longitude,
        note: note?.trim() || null,
      });
      return { ok: true, located: fix !== null };
    } catch (e) {
      const problem = e as { detail?: unknown; title?: unknown };
      const detail = [problem.detail, problem.title]
        .find((v): v is string => typeof v === 'string' && v.trim() !== '');

      return { ok: false, located: false, detail };
    }
  }
}
