import { Injectable } from '@angular/core';
import {
  ActiveWork, Lead, Payout, ProviderStats, ProviderWallet, WorkDetail, WorkStatus,
} from './provider.models';

/**
 * Provider-section data. Fixture-driven today (the shapes match the API), so swapping in the
 * generated client is a per-method change. Kept synchronous for now.
 */
@Injectable({ providedIn: 'root' })
export class ProviderService {
  readonly name = 'Atelier Nkeng';
  readonly initials = 'AN';
  readonly verified = true;

  private readonly wallet: ProviderWallet = {
    availableMinor: 640000, pendingPayoutMinor: 150000, currency: 'XAF',
  };

  private readonly stats: ProviderStats = {
    activeJobs: 3, rating: 4.9, completed90d: 34, onTimeRate: 0.94,
  };

  private readonly leads: Lead[] = [
    {
      id: 'l1', reference: 'JOB-8P4K2', title: 'Chauffe-eau ne chauffe plus', skill: 'Plomberie',
      mode: 'onsite', area: 'Bonapriso, Douala', budgetMinor: 450000, postedAgo: 'Il y a 12 min',
      accent: 'brand', details: 'Le chauffe-eau électrique de 100 L ne chauffe plus depuis hier. Accès facile, immeuble avec ascenseur.',
    },
    {
      id: 'l2', reference: 'JOB-3M9Q7', title: 'Fuite robinet cuisine', skill: 'Plomberie',
      mode: 'onsite', area: 'Akwa, Douala', budgetMinor: 120000, postedAgo: 'Il y a 1 h',
      accent: 'info', details: 'Robinet mitigeur qui goutte en continu. Pièce probablement à remplacer.',
    },
    {
      id: 'l3', reference: 'JOB-6T1B5', title: 'Débouchage canalisation', skill: 'Plomberie',
      mode: 'onsite', area: 'Bonanjo, Douala', budgetMinor: null, postedAgo: 'Il y a 3 h',
      accent: 'warning', details: 'Évier de cuisine bouché. Devis souhaité avant intervention.',
    },
  ];

  private readonly active: ActiveWork[] = [
    { id: 'j1', reference: 'JOB-7K2M9', title: 'Fuite sous l’évier', customerName: 'Jean M.', status: 'in_progress', mode: 'onsite', accent: 'brand' },
    { id: 'a2', reference: 'JOB-5RN8K', title: 'Tableau électrique', customerName: 'Sandrine B.', status: 'engaged', mode: 'onsite', accent: 'info' },
    { id: 'a3', reference: 'JOB-2HW6P', title: 'Étagères sur mesure', customerName: 'Paul T.', status: 'work_submitted', mode: 'onsite', accent: 'warning' },
  ];

  private readonly payouts: Payout[] = [
    { id: 'po1', reference: 'PO-4KX92', amountMinor: 150000, status: 'pending', date: 'Aujourd’hui' },
    { id: 'po2', reference: 'PO-9TA31', amountMinor: 425000, status: 'paid', date: '18 juil.' },
    { id: 'po3', reference: 'PO-2MB77', amountMinor: 300000, status: 'paid', date: '9 juil.' },
    { id: 'po4', reference: 'PO-6RC08', amountMinor: 180000, status: 'failed', date: '2 juil.' },
  ];

  getWallet(): ProviderWallet {
    return this.wallet;
  }

  listPayouts(): Payout[] {
    return this.payouts;
  }

  getStats(): ProviderStats {
    return this.stats;
  }

  listLeads(): Lead[] {
    return this.leads;
  }

  lead(id: string): Lead | null {
    return this.leads.find((l) => l.id === id) ?? null;
  }

  private readonly workDetails: Record<string, WorkDetail> = {
    j1: {
      id: 'j1', reference: 'JOB-7K2M9', title: 'Fuite sous l’évier', customerName: 'Jean M.',
      customerInitials: 'JM', mode: 'onsite', addressLine: 'Rue 1.234, Akwa, Douala', accent: 'brand',
      checkedIn: false, status: 'engaged', reportSubmitted: false,
    },
    a2: {
      id: 'a2', reference: 'JOB-5RN8K', title: 'Tableau électrique', customerName: 'Sandrine B.',
      customerInitials: 'SB', mode: 'onsite', addressLine: 'Bonapriso, Douala', accent: 'info',
      checkedIn: false, status: 'engaged', reportSubmitted: false,
    },
    a3: {
      id: 'a3', reference: 'JOB-2HW6P', title: 'Étagères sur mesure', customerName: 'Paul T.',
      customerInitials: 'PT', mode: 'remote', addressLine: null, accent: 'warning',
      checkedIn: false, status: 'started', reportSubmitted: false,
    },
  };

  listActive(): ActiveWork[] {
    return this.active;
  }

  /** The provider's execution view of a job. Falls back to a minimal detail from the active list. */
  workDetail(id: string): WorkDetail {
    const found = this.workDetails[id];
    if (found) {
      return found;
    }
    const work = this.active.find((w) => w.id === id);
    return {
      id, reference: work?.reference ?? 'JOB-—', title: work?.title ?? '',
      customerName: work?.customerName ?? '', customerInitials: (work?.customerName ?? '?').charAt(0),
      mode: work?.mode ?? 'onsite', addressLine: null, accent: work?.accent ?? 'muted',
      checkedIn: false, status: 'engaged', reportSubmitted: false,
    };
  }

  checkIn(id: string): void {
    const w = this.workDetails[id];
    if (w) {
      w.checkedIn = true;
      w.status = 'arrived';
    }
  }

  checkOut(id: string): void {
    const w = this.workDetails[id];
    if (w) {
      w.checkedIn = false;
    }
  }

  setWorkStatus(id: string, status: WorkStatus): void {
    const w = this.workDetails[id];
    if (w) {
      w.status = status;
    }
  }

  submitReport(id: string): void {
    const w = this.workDetails[id];
    if (w) {
      w.reportSubmitted = true;
      w.status = 'completed';
    }
  }

  /** Decline a lead — drops it from the feed (fixture). */
  declineLead(id: string): void {
    const i = this.leads.findIndex((l) => l.id === id);
    if (i >= 0) {
      this.leads.splice(i, 1);
    }
  }

  /** Submit a quote for a lead — removes it from the open feed (it becomes a pending quote). */
  submitQuote(id: string): void {
    this.declineLead(id);
  }
}
