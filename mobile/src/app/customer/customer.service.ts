import { Injectable, inject, signal } from '@angular/core';
import { ApiService } from '../api/api.service';
import { LocaleService } from '../core/locale.service';
import {
  Accent, Category, ChatSummary, EngagementMode, JobDetail, JobStatus, JobSummary, MilestoneStatus,
  NewJobInput, Provider, ProviderProfile, ProviderReview, SavedAddress, WorkspaceMessage,
  WorkspaceThread,
} from './customer.models';

/**
 * The API's PII-minimised provider resource (GET /jobs/{job}/providers) — headline + reputation, no
 * name. NOTE `id` is the provider_profiles row id; `party_id` is the handle every other endpoint
 * takes (offers, metrics, reviews). They are different values — never substitute one for the other.
 */
interface ApiProvider {
  id: string;
  party_id: string;
  headline?: string | null;
  bio?: string | null;
  verification_tier: number;
  rating_avg?: string | null;
  rating_count?: number;
  jobs_completed?: number;
  skills?: { skill_id?: string; name?: string }[];
  service_areas?: { id?: string }[];
}

/** The display-safe metrics resource (GET /providers/{party}/metrics). */
interface ApiMetrics {
  jobs_completed_90d: number;
  rating_avg?: number | null;
  rating_count: number;
  on_time_rate?: number | null;
  on_time_sample: number;
}

/** One published review (GET /providers/{party}/reviews) — author is anonymised (party id only). */
interface ApiReview {
  id: string;
  rating?: number | null;
  body?: string | null;
  published_at?: string | null;
}

/** A deterministic accent from an id, so a provider/review keeps the same colour across renders. */
const ACCENTS: Accent[] = ['brand', 'info', 'warning', 'muted'];
function accentFor(id: string): Accent {
  let sum = 0;
  for (const ch of id) {
    sum = (sum + ch.charCodeAt(0)) % ACCENTS.length;
  }
  return ACCENTS[sum];
}

/**
 * A verification tier ≥ 2 means an approved government-ID document (P6-03) — the badge the customer
 * trusts. Tier 1 (phone-only) shows no badge.
 */
function verifiedTier(tier: number): boolean {
  return tier >= 2;
}

/** Two initials from the public headline (never a personal name — the API doesn't send one). */
function headlineInitials(headline: string): string {
  const parts = headline.trim().split(/\s+/).filter(Boolean);
  return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase() || '★';
}

/**
 * The server-narrated (rule #11) lifecycle kinds render as a system chip in the thread. Free-form
 * `text`/`voice` become bubbles; anything else (a lifecycle transition) maps to an i18n chip label.
 * Attachments (media/document) share one generic chip until the native capture flow lands.
 */
const EVENT_CHIP_KEY: Record<string, string> = {
  media: 'workspace.attachment',
  document: 'workspace.attachment',
};

/** "0:14" from milliseconds — the shape a voice bubble reads best. */
function formatDuration(ms: number): string {
  const total = Math.round(ms / 1000);
  return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
}

/** Map one API message onto the thread's display model. Structured kinds collapse to a system chip. */
function mapMessage(m: {
  id: string; kind: string; body?: string | null; sender_user_id?: string | null; created_at: string;
  payload?: Record<string, unknown> | null;
  media?: { id: string; url: string; bytes?: number }[];
}, meUserId: string): WorkspaceMessage {
  const time = new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  const mine = meUserId !== '' && m.sender_user_id === meUserId;
  if (m.kind === 'text') {
    return { id: m.id, kind: 'text', mine, body: m.body ?? '', time };
  }
  if (m.kind === 'voice') {
    // The server stores the measured length, so the bubble can show it without loading the audio.
    const ms = Number(m.payload?.['duration_ms'] ?? 0);
    return {
      id: m.id,
      kind: 'voice',
      mine,
      duration: ms > 0 ? formatDuration(ms) : '',
      mediaUrl: m.media?.[0]?.url,
      time,
    };
  }
  // Everything else is a server-narrated lifecycle event → a neutral, centred system chip.
  return { id: m.id, kind: 'system', mine: false, systemKey: EVENT_CHIP_KEY[m.kind] ?? `workspace.${m.kind}`, time };
}

/** Map the API milestone status onto the app's display set. */
function mapMilestoneStatus(status: string): MilestoneStatus {
  switch (status) {
    case 'paid':
    case 'approved':
      return 'paid';
    case 'submitted':
      return 'submitted';
    case 'in_progress':
      return 'in_progress';
    default:
      return 'pending'; // pending / rejected
  }
}

/** Two-letter initials from a name; a phone-only "name" falls back to a neutral glyph. */
function initialsOf(name: string): string {
  if (/^\+?\d/.test(name.trim())) {
    return '👤';
  }
  const parts = name.trim().split(/\s+/).filter(Boolean);
  return (parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '');
}

/** Ionicon per real skill-category slug (from GET /skills); a generic icon covers anything new. */
const CATEGORY_ICONS: Record<string, string> = {
  plumbing: 'water-outline',
  electrical: 'flash-outline',
  'hvac-and-refrigeration': 'snow-outline',
  carpentry: 'hammer-outline',
  masonry: 'construct-outline',
  painting: 'brush-outline',
  cleaning: 'sparkles-outline',
  gardening: 'leaf-outline',
  'auto-mechanics': 'car-outline',
  'hair-and-beauty': 'cut-outline',
  'it-and-networks': 'laptop-outline',
  'private-tutoring': 'school-outline',
  tailoring: 'shirt-outline',
};

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
  private readonly api = inject(ApiService);
  private readonly locales = inject(LocaleService);

  /**
   * The signed-in customer. Loaded from `GET /auth/me` when a session token is present, else the
   * offline fixture stands in — so the account identity is real when connected and demoable when not.
   */
  readonly me = signal({ id: '', name: 'Jean Mballa', initials: 'JM', phone: '+237 6 99 88 77 66' });

  /** Discover's category rail — real skill categories (GET /skills) once loaded, curated fixtures until then. */
  readonly categories = signal<Category[]>([
    { id: 'plomberie', label: 'Plomberie', icon: 'water-outline' },
    { id: 'clim', label: 'Climatisation', icon: 'snow-outline' },
    { id: 'elec', label: 'Électricité', icon: 'flash-outline' },
    { id: 'design', label: 'Design', icon: 'brush-outline' },
    { id: 'menuiserie', label: 'Menuiserie', icon: 'hammer-outline' },
  ]);

  constructor() {
    void this.loadMe();
    void this.loadCategories();
    void this.loadJobs();
    void this.loadAddresses();
  }

  /** Load the customer's real saved addresses; keep the fixtures when offline. */
  private async loadAddresses(): Promise<void> {
    try {
      const addresses = await this.api.addresses();
      if (addresses.length > 0) {
        this.addresses.set(addresses.map((a) => ({
          id: a.id,
          label: a.label ?? a.quarter ?? a.city,
          line: [a.line1, a.quarter, a.city].filter(Boolean).join(', '),
        })));
      }
    } catch {
      // No session / offline — keep the fixture addresses.
    }
  }

  /** Load the customer's real jobs (with engagement summary); keep the fixtures when offline. */
  private async loadJobs(): Promise<void> {
    try {
      const jobs = await this.api.jobs();
      this.jobs.set(jobs.map((j) => ({
        id: j.id,
        reference: j.reference,
        title: j.title,
        status: j.status as JobStatus,
        providerName: j.engagement?.provider_name ?? null,
        amountMinor: j.engagement?.agreed_amount_minor ?? j.budget?.amount_minor ?? 0,
        milestonesDone: j.engagement?.milestones_done ?? 0,
        milestonesTotal: j.engagement?.milestones_total ?? 0,
      })));
    } catch {
      // No session / offline — keep the demo fixtures.
    }
  }

  private async loadMe(): Promise<void> {
    try {
      const u = await this.api.me();
      const name = u.display_name ?? u.phone_e164;
      this.me.set({ id: u.id, name, initials: initialsOf(name), phone: u.phone_e164 });
    } catch {
      // No session / offline — keep the fixture identity.
    }
  }

  /** Real skill categories drive the discover rail; the curated fixtures stand in when offline. */
  private async loadCategories(): Promise<void> {
    try {
      const skills = await this.api.skills(this.locales.current);
      if (skills.length > 0) {
        this.categories.set(skills.map((s) => ({
          id: s.slug,
          label: s.name ?? s.slug,
          icon: CATEGORY_ICONS[s.slug] ?? 'briefcase-outline',
          skillId: s.id,
          leaves: (s.children ?? []).map((c) => ({ id: c.id, label: c.name ?? c.slug })),
        })));
      }
    } catch {
      // Offline — keep the curated fixture categories.
    }
  }

  private readonly providers: Provider[] = [
    { id: 'p1', name: 'Atelier Nkeng', initials: 'AN', skill: 'Plomberie', rating: 4.9, mode: 'onsite', distanceKm: 2.1, verified: true, accent: 'brand' },
    { id: 'p2', name: 'Marie Fotso', initials: 'MF', skill: 'Design graphique', rating: 4.8, mode: 'remote', distanceKm: null, verified: true, accent: 'info' },
    { id: 'p3', name: 'Douala Cool Services', initials: 'DC', skill: 'Climatisation', rating: 4.7, mode: 'onsite', distanceKm: 3.4, verified: false, accent: 'warning' },
    { id: 'p4', name: 'Yaoundé Élec', initials: 'YE', skill: 'Électricité', rating: 4.6, mode: 'onsite', distanceKm: 5.2, verified: true, accent: 'muted' },
    { id: 'p5', name: 'Fresh Design Studio', initials: 'FD', skill: 'Design graphique', rating: 4.5, mode: 'remote', distanceKm: null, verified: false, accent: 'info' },
  ];

  /** The customer's jobs — real (GET /jobs) once loaded, the demo fixtures until then / when offline. */
  readonly jobs = signal<JobSummary[]>([
    { id: 'j1', reference: 'JOB-7K2M9', title: 'Fuite sous l’évier', status: 'in_progress', providerName: 'Atelier Nkeng', amountMinor: 900000, milestonesDone: 1, milestonesTotal: 2 },
    { id: 'j2', reference: 'JOB-Q4T1A', title: 'Identité visuelle', status: 'work_submitted', providerName: 'Marie Fotso', amountMinor: 450000, milestonesDone: 1, milestonesTotal: 1 },
    { id: 'j3', reference: 'JOB-9BZ3C', title: 'Installation split', status: 'engaged', providerName: 'Douala Cool Services', amountMinor: 1250000, milestonesDone: 0, milestonesTotal: 2 },
    { id: 'j4', reference: 'JOB-5RN8K', title: 'Tableau électrique', status: 'completed', providerName: 'Yaoundé Élec', amountMinor: 3100000, milestonesDone: 3, milestonesTotal: 3 },
    { id: 'j5', reference: 'JOB-2HW6P', title: 'Étagères sur mesure', status: 'open', providerName: null, amountMinor: 620000, milestonesDone: 0, milestonesTotal: 0 },
  ]);

  /** The customer's saved addresses — real (GET /addresses) once loaded, fixtures until then / offline. */
  readonly addresses = signal<SavedAddress[]>([
    { id: 'a1', label: 'Domicile', line: 'Rue 1.234, Akwa, Douala' },
    { id: 'a2', label: 'Bureau', line: 'Boulevard de la Liberté, Bonanjo, Douala' },
  ]);

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
      // A fixture thread has no engagement to subscribe to — it stays static, which is correct.
      engagementId: null,
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

  /** The public provider profile (reviews + display-safe metrics). Falls back to a sensible default. */
  provider(id: string): ProviderProfile {
    return this.profiles[id] ?? this.profiles['p1'];
  }

  /** Providers filtered by the discover mode segment. `both` returns everything. */
  listProviders(mode: EngagementMode | 'both'): Provider[] {
    return mode === 'both' ? this.providers : this.providers.filter((p) => p.mode === mode);
  }

  listAddresses(): SavedAddress[] {
    return this.addresses();
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
    const job = this.jobs().find((j) => j.id === id);
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
   * The full job detail from the API (GET /jobs/{id}) — provider, money, and milestone timeline.
   * Escrow-held / released are derived from the milestones (paid = released, the rest still held),
   * a display view that matches the fixture semantics without a second ledger round-trip. Returns
   * null when there's no session / the backend is unreachable, so callers keep the fixture.
   */
  async fetchJobDetail(id: string): Promise<JobDetail | null> {
    try {
      const j = await this.api.job(id);
      const eng = j.engagement ?? null;
      const milestones = (eng?.milestones ?? []).map((m) => ({
        id: m.id, title: m.title, amountMinor: m.amount_minor, status: mapMilestoneStatus(m.status),
      }));
      const released = milestones.filter((m) => m.status === 'paid').reduce((s, m) => s + m.amountMinor, 0);
      const agreed = eng?.agreed_amount_minor ?? j.budget?.amount_minor ?? 0;
      const providerName = eng?.provider_name ?? null;
      const loc = j.location ?? null;
      const addressLine = loc ? (loc.line1 ?? [loc.quarter, loc.city].filter(Boolean).join(', ')) : null;

      return {
        id: j.id,
        reference: j.reference,
        title: j.title,
        status: j.status as JobStatus,
        mode: j.engagement_mode,
        providerName,
        providerInitials: providerName ? providerName.slice(0, 2).toUpperCase() : null,
        providerId: null,
        accent: 'brand',
        addressLine,
        currency: eng?.currency ?? 'XAF',
        agreedMinor: agreed,
        escrowHeldMinor: Math.max(0, agreed - released),
        releasedMinor: released,
        milestones,
      };
    } catch {
      return null;
    }
  }

  /** Approve a milestone on the real backend then re-fetch the detail; falls back to the fixture. */
  async approveAndRefresh(jobId: string, milestoneId: string): Promise<JobDetail> {
    try {
      await this.api.approveMilestone(milestoneId);
      const real = await this.fetchJobDetail(jobId);
      if (real !== null) {
        return real;
      }
    } catch {
      // fall through to the fixture path (offline / demo job)
    }
    this.approveMilestone(jobId, milestoneId);
    return this.jobDetail(jobId);
  }

  /**
   * Post a new request — mirrors CreateJob + PublishJob (doc 06). When a real leaf skill is chosen
   * and the backend is reachable, it creates + publishes a real job and re-loads the list; otherwise
   * it falls back to prepending a demo job so the offline flow still works. Returns the job id.
   */
  async createJob(input: NewJobInput): Promise<string> {
    if (input.skillId !== null) {
      try {
        const job = await this.api.createJob({
          skill_id: input.skillId,
          engagement_mode: input.mode,
          title: input.title.trim() || 'Nouvelle demande',
          description: input.details.trim() || undefined,
          address_id: input.mode === 'remote' ? undefined : (input.addressId ?? undefined),
          budget_minor: input.budgetMinor ?? undefined,
        });
        await this.api.publishJob(job.id);
        await this.loadJobs();
        return job.id;
      } catch {
        // fall through to the fixture path
      }
    }

    const id = `new-${this.jobs().length + 1}`;
    const category = this.categories().find((c) => c.id === input.categoryId);
    const job: JobSummary = {
      id,
      reference: `JOB-${id.toUpperCase()}`,
      title: input.title.trim() || (category?.label ?? ''),
      status: 'open',
      providerName: null,
      amountMinor: input.budgetMinor ?? 0,
      milestonesDone: 0,
      milestonesTotal: 0,
    };
    this.jobs.update((list) => [job, ...list]);
    return id;
  }

  listChats(): ChatSummary[] {
    return this.chats;
  }

  thread(id: string): WorkspaceThread | null {
    return this.threads[id] ?? this.threads['j1'] ?? null;
  }

  /**
   * The real workspace thread (GET /jobs/{id}/messages) with its header drawn from the job. The chat
   * IS the state machine (doc 06): text/voice are bubbles, every server-narrated lifecycle kind is a
   * system chip. Returns null — so the caller keeps the fixture thread — when there's no session, the
   * job has no conversation yet, or the backend is unreachable.
   */
  async fetchThread(jobId: string): Promise<WorkspaceThread | null> {
    try {
      const [detail, thread] = await Promise.all([
        this.fetchJobDetail(jobId),
        this.api.messages(jobId),
      ]);
      if (detail === null) {
        return null;
      }
      const name = detail.providerName ?? detail.title;
      return {
        id: detail.id,
        providerName: name,
        initials: detail.providerInitials ?? initialsOf(name),
        reference: detail.reference,
        skill: detail.title,
        status: detail.status,
        accent: detail.accent,
        messages: thread.messages.map((m) => mapMessage(m, this.me().id)),
        engagementId: thread.engagementId,
      };
    } catch {
      return null;
    }
  }

  /**
   * Map one BROADCAST message onto the thread's display model. Reverb sends the same shape the REST
   * read does (MessagePosted::broadcastWith), so this is the fetched-message mapper — a live message
   * and a fetched one render through identical code.
   */
  mapLiveMessage(m: {
    id: string; kind: string; body?: string | null; sender_user_id?: string | null; created_at: string;
  }): WorkspaceMessage {
    return mapMessage(m, this.me().id);
  }

  /**
   * Send a recorded voice note (P4-05). Returns false when the server refuses it — an empty or
   * over-long recording comes back 422 and there is nothing useful to retry automatically.
   */
  async sendVoiceNote(jobId: string, take: { blob: Blob; durationMs: number; filename: string }): Promise<boolean> {
    try {
      await this.api.postVoiceMessage(jobId, take.blob, take.filename, take.durationMs);
      return true;
    } catch {
      return false;
    }
  }

  /** Fetch a voice note's audio as a playable object URL (the media route needs the Bearer). */
  async voiceObjectUrl(url: string): Promise<string> {
    return this.api.mediaObjectUrl(url);
  }

  /** Post a free-form message to the thread, then re-fetch it. Returns null on failure (offline / demo). */
  async sendMessage(jobId: string, body: string): Promise<WorkspaceThread | null> {
    try {
      await this.api.postMessage(jobId, body);
      return await this.fetchThread(jobId);
    } catch {
      return null;
    }
  }

  /**
   * The label for one of a provider's listed skills. The API now sends it (bilingual, in the
   * caller's locale), so that wins; the taxonomy lookup stays as the fallback for a cached response
   * from before the field existed.
   */
  private skillLabel(skill: { skill_id?: string; name?: string }): string {
    if (typeof skill.name === 'string' && skill.name !== '') {
      return skill.name;
    }
    for (const cat of this.categories()) {
      const leaf = (cat.leaves ?? []).find((l) => l.id === skill.skill_id);
      if (leaf) {
        return leaf.label;
      }
    }
    return '';
  }

  /** Whether a matched provider serves an on-site area (has a service area) or works remotely. */
  private providerMode(p: ApiProvider): EngagementMode {
    return (p.service_areas ?? []).length > 0 ? 'onsite' : 'remote';
  }

  /** Map one API provider (search result) onto a discover card. Headline stands in for the name (PII). */
  private mapProviderCard(p: ApiProvider): Provider {
    const headline = p.headline ?? '';
    const skill = (p.skills ?? []).map((s) => this.skillLabel(s)).find(Boolean) ?? '';
    return {
      // The PARTY id — this card's id is what the profile screen and "send an offer" pass onward.
      id: p.party_id,
      name: headline || skill,
      initials: headlineInitials(headline || skill),
      skill,
      rating: p.rating_avg !== null && p.rating_avg !== undefined ? Number(p.rating_avg) : 0,
      mode: this.providerMode(p),
      distanceKm: null, // the resource never leaks a precise distance (PII); rank order is enough
      verified: verifiedTier(p.verification_tier),
      accent: accentFor(p.party_id),
    };
  }

  /**
   * The real providers matched to a job (GET /jobs/{job}/providers) — owner-only. Returns null when
   * there's no session / the job isn't the caller's / offline, so the caller keeps the demo list.
   */
  async fetchJobProviders(jobId: string): Promise<Provider[] | null> {
    try {
      const list: ApiProvider[] = await this.api.jobProviders(jobId);
      return list.map((p) => this.mapProviderCard(p));
    } catch {
      return null;
    }
  }

  /**
   * The real public provider profile: the matched-provider resource (for the headline/skills/bio) plus
   * the display-safe metrics (P6-12) and published double-blind reviews (P6-08). Needs the job context
   * because the only public source of a provider's headline is the job's match list. Returns null on
   * failure so the caller keeps the fixture profile.
   */
  async fetchProviderProfile(partyId: string, jobId: string): Promise<ProviderProfile | null> {
    try {
      const [providers, metrics, reviews]: [ApiProvider[], ApiMetrics, ApiReview[]] = await Promise.all([
        this.api.jobProviders(jobId),
        this.api.providerMetrics(partyId),
        this.api.providerReviews(partyId),
      ]);
      const base = providers.find((p) => p.party_id === partyId);
      if (!base) {
        return null;
      }
      const headline = base.headline ?? '';
      const skills = (base.skills ?? []).map((s) => this.skillLabel(s)).filter(Boolean);
      return {
        id: partyId,
        name: headline || (skills[0] ?? ''),
        initials: headlineInitials(headline || (skills[0] ?? '')),
        headline: skills[0] ?? headline,
        accent: accentFor(partyId),
        verified: verifiedTier(base.verification_tier),
        mode: this.providerMode(base),
        city: '', // the public resource never exposes a precise location (PII)
        ratingAvg: metrics.rating_avg ?? null,
        ratingCount: metrics.rating_count,
        jobsCompleted90d: metrics.jobs_completed_90d,
        onTimeRate: metrics.on_time_rate ?? null,
        responseTime: '', // not a public signal yet — the stat card hides when empty
        memberSince: '', // not exposed publicly — the line hides when empty
        skills,
        about: base.bio ?? '',
        reviews: reviews.map((r) => this.mapReview(r)),
      };
    } catch {
      return null;
    }
  }

  /** Map one published review. The author is anonymised server-side — the client shows no name. */
  private mapReview(r: ApiReview): ProviderReview {
    const date = r.published_at ? new Date(r.published_at).toLocaleDateString() : '';
    return {
      id: r.id,
      authorInitials: '★',
      authorName: '', // PII-minimised: the public feed carries no author identity
      rating: r.rating ?? 0,
      comment: r.body ?? '',
      date,
      mode: 'onsite',
      accent: accentFor(r.id),
    };
  }

  /**
   * Send a direct offer to a provider for one of the customer's jobs (POST /jobs/{job}/offers). Returns
   * true when the offer lands; false on failure (offline / already engaged / blocked) so the caller can
   * surface a friendly message without crashing the demo flow.
   */
  async sendOffer(jobId: string, providerPartyId: string, message?: string): Promise<boolean> {
    try {
      await this.api.createDirectOffer(jobId, providerPartyId, message);
      return true;
    } catch {
      return false;
    }
  }
}
