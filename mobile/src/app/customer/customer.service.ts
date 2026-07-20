import { Injectable } from '@angular/core';
import {
  Category, ChatSummary, EngagementMode, JobSummary, Provider, WorkspaceThread,
} from './customer.models';

/**
 * Customer-section data.
 *
 * Today this serves representative fixtures so the screens are complete and demoable offline; the
 * shapes are exactly what the API returns, so swapping in the generated client (`src/app/api`) is a
 * per-method change and no screen has to move. Kept synchronous for now — the API swap will return
 * observables/promises at these same boundaries.
 */
@Injectable({ providedIn: 'root' })
export class CustomerService {
  private readonly providers: Provider[] = [
    { id: 'p1', name: 'Atelier Nkeng', initials: 'AN', skill: 'Plomberie', rating: 4.9, mode: 'onsite', distanceKm: 2.1, verified: true, accent: 'brand' },
    { id: 'p2', name: 'Marie Fotso', initials: 'MF', skill: 'Design graphique', rating: 4.8, mode: 'remote', distanceKm: null, verified: true, accent: 'info' },
    { id: 'p3', name: 'Douala Cool Services', initials: 'DC', skill: 'Climatisation', rating: 4.7, mode: 'onsite', distanceKm: 3.4, verified: false, accent: 'warning' },
    { id: 'p4', name: 'Yaoundé Élec', initials: 'YE', skill: 'Électricité', rating: 4.6, mode: 'onsite', distanceKm: 5.2, verified: true, accent: 'muted' },
    { id: 'p5', name: 'Fresh Design Studio', initials: 'FD', skill: 'Design graphique', rating: 4.5, mode: 'remote', distanceKm: null, verified: false, accent: 'info' },
  ];

  private readonly cats: Category[] = [
    { id: 'plomberie', label: 'Plomberie', icon: 'water-outline' },
    { id: 'clim', label: 'Climatisation', icon: 'snow-outline' },
    { id: 'elec', label: 'Électricité', icon: 'flash-outline' },
    { id: 'design', label: 'Design', icon: 'brush-outline' },
    { id: 'menuiserie', label: 'Menuiserie', icon: 'hammer-outline' },
  ];

  private readonly jobs: JobSummary[] = [
    { id: 'j1', reference: 'JOB-7K2M9', title: 'Fuite sous l’évier', status: 'in_progress', providerName: 'Atelier Nkeng', amountMinor: 900000, milestonesDone: 1, milestonesTotal: 2 },
    { id: 'j2', reference: 'JOB-Q4T1A', title: 'Identité visuelle', status: 'work_submitted', providerName: 'Marie Fotso', amountMinor: 450000, milestonesDone: 1, milestonesTotal: 1 },
    { id: 'j3', reference: 'JOB-9BZ3C', title: 'Installation split', status: 'engaged', providerName: 'Douala Cool Services', amountMinor: 1250000, milestonesDone: 0, milestonesTotal: 2 },
    { id: 'j4', reference: 'JOB-5RN8K', title: 'Tableau électrique', status: 'completed', providerName: 'Yaoundé Élec', amountMinor: 3100000, milestonesDone: 3, milestonesTotal: 3 },
    { id: 'j5', reference: 'JOB-2HW6P', title: 'Étagères sur mesure', status: 'open', providerName: null, amountMinor: 620000, milestonesDone: 0, milestonesTotal: 0 },
  ];

  private readonly chats: ChatSummary[] = [
    { id: 'j1', providerName: 'Atelier Nkeng', initials: 'AN', reference: 'JOB-7K2M9', preview: 'Je peux passer demain matin.', time: '09:22', unread: 2, accent: 'brand' },
    { id: 'j2', providerName: 'Marie Fotso', initials: 'MF', reference: 'JOB-Q4T1A', preview: 'Les maquettes sont prêtes.', time: 'Hier', unread: 0, accent: 'info' },
    { id: 'j3', providerName: 'Douala Cool Services', initials: 'DC', reference: 'JOB-9BZ3C', preview: 'Bien reçu, merci.', time: 'Lun', unread: 0, accent: 'warning' },
  ];

  private readonly threads: Record<string, WorkspaceThread> = {
    j1: {
      id: 'j1',
      providerName: 'Atelier Nkeng',
      initials: 'AN',
      reference: 'JOB-7K2M9',
      skill: 'Plomberie',
      status: 'in_progress',
      accent: 'brand',
      messages: [
        { id: 'm1', kind: 'text', mine: false, body: 'Bonjour, j’ai regardé les photos. Je peux passer demain matin pour un devis.', time: '08:12' },
        { id: 'm2', kind: 'text', mine: true, body: 'Parfait, merci !', time: '08:14' },
        { id: 'm3', kind: 'quote', mine: false, time: '08:40', quote: { version: 1, totalMinor: 900000, depositMinor: 200000, balanceMinor: 700000 } },
        { id: 'm4', kind: 'system', mine: false, systemKey: 'workspace.quote_accepted' },
        { id: 'm5', kind: 'system', mine: false, systemKey: 'workspace.on_the_way' },
        { id: 'm6', kind: 'voice', mine: false, duration: '0:14', time: '09:22' },
        { id: 'm7', kind: 'milestone', mine: false, milestone: { amountMinor: 200000 } },
      ],
    },
  };

  listCategories(): Category[] {
    return this.cats;
  }

  /** Providers filtered by the discover mode segment. `both` returns everything. */
  listProviders(mode: EngagementMode | 'both'): Provider[] {
    return mode === 'both' ? this.providers : this.providers.filter((p) => p.mode === mode);
  }

  listJobs(): JobSummary[] {
    return this.jobs;
  }

  listChats(): ChatSummary[] {
    return this.chats;
  }

  thread(id: string): WorkspaceThread | null {
    return this.threads[id] ?? this.threads['j1'] ?? null;
  }
}
