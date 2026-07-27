import { Injectable } from '@angular/core';
import {
  Category, ChatSummary, EngagementMode, JobDetail, JobSummary, NewJobInput, Provider,
  ProviderProfile, SavedAddress, WorkspaceThread,
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

  private readonly addresses: SavedAddress[] = [
    { id: 'a1', label: 'Domicile', line: 'Rue 1.234, Akwa, Douala' },
    { id: 'a2', label: 'Bureau', line: 'Boulevard de la Liberté, Bonanjo, Douala' },
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

  private readonly profiles: Record<string, ProviderProfile> = {
    p1: {
      id: 'p1', name: 'Atelier Nkeng', initials: 'AN', headline: 'Plomberie', accent: 'brand',
      verified: true, mode: 'onsite', city: 'Douala, Akwa', ratingAvg: 4.76, ratingCount: 128,
      jobsCompleted90d: 34, onTimeRate: 0.94, responseTime: '~1h', memberSince: '2024',
      skills: ['Plomberie', 'Fuites', 'Chauffe-eau', 'Sanitaires'],
      about: 'Équipe de plombiers basée à Akwa. Interventions rapides, devis clairs, garantie sur la main-d’œuvre.',
      reviews: [
        { id: 'r1', authorInitials: 'JM', authorName: 'Jean M.', rating: 5, comment: 'Ponctuel et propre. La fuite est réglée depuis un mois.', date: 'Il y a 3 j', mode: 'onsite', accent: 'brand' },
        { id: 'r2', authorInitials: 'SB', authorName: 'Sandrine B.', rating: 5, comment: 'Très professionnel, devis respecté au franc près.', date: 'Il y a 2 sem', mode: 'onsite', accent: 'info' },
        { id: 'r3', authorInitials: 'PT', authorName: 'Paul T.', rating: 4, comment: 'Bon travail, un léger retard le matin.', date: 'Il y a 1 mois', mode: 'onsite', accent: 'muted' },
      ],
    },
    p2: {
      id: 'p2', name: 'Marie Fotso', initials: 'MF', headline: 'Design graphique', accent: 'info',
      verified: true, mode: 'remote', city: 'À distance', ratingAvg: 4.8, ratingCount: 41,
      jobsCompleted90d: 12, onTimeRate: 0.98, responseTime: '~2h', memberSince: '2023',
      skills: ['Logo', 'Identité visuelle', 'Print', 'Réseaux sociaux'],
      about: 'Designer indépendante. Identités de marque et supports print pour PME camerounaises.',
      reviews: [
        { id: 'r1', authorInitials: 'AK', authorName: 'Aline K.', rating: 5, comment: 'Maquettes livrées avant l’échéance. Superbe travail.', date: 'Il y a 5 j', mode: 'remote', accent: 'info' },
        { id: 'r2', authorInitials: 'RN', authorName: 'René N.', rating: 5, comment: 'Communication fluide, plusieurs allers-retours sans souci.', date: 'Il y a 3 sem', mode: 'remote', accent: 'brand' },
      ],
    },
    // A newly-onboarded provider: too few reviews to show an on-time rate (P6-12 floor),
    // and the display rating is still shrinking toward the prior — never a bare "5.0 (1)".
    p3: {
      id: 'p3', name: 'Douala Cool Services', initials: 'DC', headline: 'Climatisation', accent: 'warning',
      verified: false, mode: 'onsite', city: 'Douala, Bonapriso', ratingAvg: 4.21, ratingCount: 3,
      jobsCompleted90d: 2, onTimeRate: null, responseTime: '~4h', memberSince: '2026',
      skills: ['Installation split', 'Entretien', 'Recharge gaz'],
      about: 'Nouvelle équipe spécialisée en climatisation résidentielle et petits commerces.',
      reviews: [
        { id: 'r1', authorInitials: 'FE', authorName: 'Franck E.', rating: 5, comment: 'Installation nickel, équipe sympa.', date: 'Il y a 1 sem', mode: 'onsite', accent: 'warning' },
      ],
    },
  };

  listCategories(): Category[] {
    return this.cats;
  }

  /** The public provider profile (reviews + display-safe metrics). Falls back to a sensible default. */
  provider(id: string): ProviderProfile {
    return this.profiles[id] ?? this.profiles['p1'];
  }

  /** Providers filtered by the discover mode segment. `both` returns everything. */
  listProviders(mode: EngagementMode | 'both'): Provider[] {
    return mode === 'both' ? this.providers : this.providers.filter((p) => p.mode === mode);
  }

  listJobs(): JobSummary[] {
    return this.jobs;
  }

  listAddresses(): SavedAddress[] {
    return this.addresses;
  }

  private readonly details: Record<string, JobDetail> = {
    j1: {
      id: 'j1', reference: 'JOB-7K2M9', title: 'Fuite sous l’évier', status: 'in_progress', mode: 'onsite',
      providerName: 'Atelier Nkeng', providerInitials: 'AN', providerId: 'p1', accent: 'brand',
      addressLine: 'Rue 1.234, Akwa, Douala', currency: 'XAF',
      agreedMinor: 900000, escrowHeldMinor: 700000, releasedMinor: 200000,
      milestones: [
        { id: 'm1', title: 'Deposit', amountMinor: 200000, status: 'paid' },
        { id: 'm2', title: 'Balance', amountMinor: 700000, status: 'submitted' },
      ],
    },
    j3: {
      id: 'j3', reference: 'JOB-9BZ3C', title: 'Installation split', status: 'engaged', mode: 'onsite',
      providerName: 'Douala Cool Services', providerInitials: 'DC', providerId: 'p3', accent: 'warning',
      addressLine: 'Boulevard de la Liberté, Bonanjo, Douala', currency: 'XAF',
      agreedMinor: 1250000, escrowHeldMinor: 0, releasedMinor: 0,
      milestones: [
        { id: 'm1', title: 'Deposit', amountMinor: 300000, status: 'pending' },
        { id: 'm2', title: 'Balance', amountMinor: 950000, status: 'pending' },
      ],
    },
  };

  /** The full job overview. Falls back to a minimal detail synthesised from the list summary. */
  jobDetail(id: string): JobDetail {
    const found = this.details[id];
    if (found) {
      return found;
    }
    const job = this.jobs.find((j) => j.id === id);
    return {
      id, reference: job?.reference ?? 'JOB-—', title: job?.title ?? '', status: job?.status ?? 'open',
      mode: 'onsite', providerName: job?.providerName ?? null, providerInitials: null, providerId: null,
      accent: 'muted', addressLine: null, currency: 'XAF',
      agreedMinor: job?.amountMinor ?? 0, escrowHeldMinor: 0, releasedMinor: 0, milestones: [],
    };
  }

  /** Approve a submitted milestone — releases its escrow slice (fixture: mark paid, move to released). */
  approveMilestone(jobId: string, milestoneId: string): void {
    const detail = this.details[jobId];
    const milestone = detail?.milestones.find((m) => m.id === milestoneId);
    if (!detail || !milestone || milestone.status === 'paid') {
      return;
    }
    milestone.status = 'paid';
    detail.escrowHeldMinor = Math.max(0, detail.escrowHeldMinor - milestone.amountMinor);
    detail.releasedMinor += milestone.amountMinor;
  }

  /**
   * Post a new request — mirrors CreateJob + PublishJob (doc 06): a fresh `open` job with no
   * provider yet. A remote job carries no address (the conditional-address rule). Returns the id.
   */
  createJob(input: NewJobInput): string {
    const seq = this.jobs.length + 1;
    const id = `new-${seq}`;
    const category = this.cats.find((c) => c.id === input.categoryId);
    this.jobs.unshift({
      id,
      reference: `JOB-${id.toUpperCase()}`,
      title: input.title.trim() || (category?.label ?? ''),
      status: 'open',
      providerName: null,
      amountMinor: input.budgetMinor ?? 0,
      milestonesDone: 0,
      milestonesTotal: 0,
    });
    return id;
  }

  listChats(): ChatSummary[] {
    return this.chats;
  }

  thread(id: string): WorkspaceThread | null {
    return this.threads[id] ?? this.threads['j1'] ?? null;
  }
}
