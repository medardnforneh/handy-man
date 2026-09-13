import { Injectable, inject, signal } from '@angular/core';
import type { MobileRail } from '../core/payment-methods';
import { TranslateService } from '@ngx-translate/core';
import { ApiService, DisputeCategory } from '../api/api.service';
import { SessionScope } from '../core/session-scope.service';
import { LocaleService } from '../core/locale.service';
import { OfflineCache } from '../core/offline/offline-cache.service';
import { WriteOutcome, WriteQueue } from '../core/offline/write-queue.service';
import {
  Accent, Category, ChatSummary, EngagementMode, JobDetail, JobQuote, JobStatus, JobSummary,
  MilestoneStatus, NewJobInput, Provider, ProviderProfile, ProviderReview, SavedAddress,
  WorkspaceMessage, WorkspaceThread,
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

/**
 * The i18n key for a chat row whose last message was NOT free-form text. The list must not print a
 * server-composed sentence — the reader's language is a client fact — so the kind is turned into a
 * key here, reusing the same `workspace.*` copy the thread already renders for that event.
 */
function chatPreviewKey(kind: string): string {
  if (kind === 'voice') {
    return 'chats.voice_note';
  }
  return EVENT_CHIP_KEY[kind] ?? `workspace.${kind}`;
}

/**
 * A spelled-out date, for something that matters months from now — when a warranty runs out.
 *
 * Same reasoning as `shortTime` below, and worth restating because it is easy to reach for Angular's
 * `date` pipe instead: this app registers no Angular locale data, so that pipe formats in en-US and
 * would print "November 13, 2026" underneath French chrome.
 */
function longDate(iso: string): string {
  const at = new Date(iso);
  if (Number.isNaN(at.getTime())) {
    return '';
  }
  return at.toLocaleDateString([], { day: 'numeric', month: 'long', year: 'numeric' });
}

/**
 * A list timestamp: the clock for today's messages, a short date for anything older. Both come from
 * `toLocale*` so they follow the device's own conventions rather than an English format — and it
 * needs no extra translated strings ("Yesterday" would).
 */
function shortTime(iso: string): string {
  const at = new Date(iso);
  const isToday = at.toDateString() === new Date().toDateString();
  return isToday
    ? at.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    : at.toLocaleDateString([], { day: '2-digit', month: 'short' });
}

/**
 * Lower-case and strip accents, for matching what someone typed against a label. Half this
 * taxonomy is French: without folding, "electricite" would not match "Électricité", and a customer
 * on a phone keyboard will not reach for the accent.
 */
function fold(value: string): string {
  return value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
}

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
  // A submitted deliverable is the one narrated event the customer has to ACT on: remote work is
  // finished by accepting it, and nothing else in the app can. It carries the deliverable's id in
  // its payload, which is exactly what the review endpoint needs, so it becomes a card with two
  // buttons rather than a chip that states a fact and offers nothing.
  if (m.kind === 'deliverable_submitted') {
    const id = m.payload?.['deliverable_id'];
    const title = m.payload?.['title'];
    if (typeof id === 'string') {
      return {
        id: m.id,
        kind: 'deliverable',
        mine,
        time,
        deliverable: { id, title: typeof title === 'string' ? title : '' },
      };
    }
  }

  // A warranty is narrated for one reason: it is the only way the customer learns they have one.
  // Warranties have no read endpoint, so this message IS their copy — and the id in its payload is
  // the only thing a claim can be filed against.
  if (m.kind === 'warranty_issued') {
    const id = m.payload?.['warranty_id'];
    const expires = m.payload?.['expires_at'];
    if (typeof id === 'string') {
      return {
        id: m.id,
        kind: 'warranty',
        mine,
        time,
        warranty: { id, expiresOn: typeof expires === 'string' ? longDate(expires) : '' },
      };
    }
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

/**
 * What a job's engagement says about the money, for the home screen's escrow figure (handoff:
 * "Escrow figures — derived from the ledger, not stored on the job"). Same derivation as the job
 * detail: paid milestones are released, the rest of the agreed amount is still held; a submitted
 * milestone is the one thing on the list that needs this person.
 */
function moneyOf(engagement: { agreed_amount_minor?: number; milestones?: Array<{ amount_minor: number; status: string }> } | null | undefined): Pick<JobSummary, 'escrowHeldMinor' | 'releasedMinor' | 'needsApproval'> {
  if (!engagement) {
    return { escrowHeldMinor: 0, releasedMinor: 0, needsApproval: false };
  }
  const milestones = engagement.milestones ?? [];
  const released = milestones
    .filter((m) => mapMilestoneStatus(m.status) === 'paid')
    .reduce((sum, m) => sum + m.amount_minor, 0);
  return {
    escrowHeldMinor: Math.max(0, (engagement.agreed_amount_minor ?? 0) - released),
    releasedMinor: released,
    needsApproval: milestones.some((m) => mapMilestoneStatus(m.status) === 'submitted'),
  };
}

/**
 * Two-letter initials from a name. A phone-only "name" has no initials — "+2" is not who anyone is
 * — so it returns nothing and the avatar falls back to its person glyph. (It used to return a 👤
 * EMOJI, which rendered as a dark blob in a brand-coloured circle and matched no other icon in the
 * app; the fallback is now the same outline icon every empty avatar uses.)
 */
function initialsOf(name: string): string {
  if (/^\+?\d/.test(name.trim())) {
    return '';
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
  private readonly session = inject(SessionScope);
  private readonly locales = inject(LocaleService);
  /** For the few labels the SERVER names as a key and the client has to say in words. */
  private readonly translate = inject(TranslateService);
  private readonly queue = inject(WriteQueue);
  private readonly cache = inject(OfflineCache);

  /**
   * The signed-in customer, from `GET /auth/me`.
   *
   * Starts EMPTY, not as a persona. It used to default to "Jean Mballa · +237 6 99 88 77 66", and
   * that default is what you saw whenever the call didn't land — an expired access token is a 401,
   * and a 401 here meant the app greeted you by a stranger's name and put their initials in your
   * avatar. Someone else's identity is the one placeholder this app must never show: a demo lead in
   * a list is an illustration, a name in YOUR avatar is a claim about you. Screens read the signal,
   * so the real identity replaces this the moment it arrives, and until then they say nothing
   * rather than something false.
   */
  readonly me = signal({ id: '', name: '', initials: '', phone: '' });

  /** Discover's category rail — real skill categories (GET /skills) once loaded, curated fixtures until then. */
  readonly categories = signal<Category[]>([
    { id: 'plomberie', label: 'Plomberie', icon: 'water-outline' },
    { id: 'clim', label: 'Climatisation', icon: 'snow-outline' },
    { id: 'elec', label: 'Électricité', icon: 'flash-outline' },
    { id: 'design', label: 'Design', icon: 'brush-outline' },
    { id: 'menuiserie', label: 'Menuiserie', icon: 'hammer-outline' },
  ]);

  constructor() {
    // Everything below belongs to whoever is signed in. This service is a root singleton and the
    // web build never reloads the page between sessions, so without this the next person to log in
    // on the same device sees the last one's name, phone, jobs and saved addresses until each
    // screen's own fetch replaces them (P5-02 clears the same data from disk for this reason).
    this.session.register(() => {
      this.me.set({ id: '', name: '', initials: '', phone: '' });
      this.jobs.set([]);
      this.jobsLoaded.set(false);
      this.addresses.set([]);
      // Categories are the public taxonomy, not this person's data, so they stay.
    });

    void this.loadMe();
    void this.loadCategories();
    void this.loadJobs();
    void this.loadAddresses();

    // Whoever moves the language — the user picking one, or the account's own being adopted at
    // launch — the trade names have to follow it. This service outlives every screen, so the
    // subscription is deliberately never torn down.
    this.translate.onLangChange.subscribe(() => void this.loadCategories());
  }

  /**
   * Load the customer's real saved addresses.
   *
   * Public so the screen that saves one can refresh the list without a page reload. Note it sets
   * even an EMPTY list: "you have no saved addresses" is a true answer, and the old guard
   * (`if (addresses.length > 0)`) meant a customer with none kept looking at the fixtures forever.
   */
  async loadAddresses(): Promise<void> {
    try {
      const addresses = await this.api.addresses();
      this.addresses.set(addresses.map((a) => ({
        id: a.id,
        label: a.label ?? a.quarter ?? a.city,
        line: [a.line1, a.quarter, a.city].filter(Boolean).join(', '),
      })));
    } catch {
      // No session / offline — leave whatever the last successful read produced.
    }
  }

  /**
   * Save an address and refresh the list (P1-06).
   *
   * Records the location_tracking consent first, because the endpoint is gated on it and the
   * screen has just told the user their location is about to be used — asking the server for the
   * grant after showing that sentence is the honest order.
   */
  async createAddress(input: {
    label: string;
    line1: string;
    quarter: string;
    city: string;
    landmarkNote: string;
    latitude: number;
    longitude: number;
  }): Promise<boolean> {
    try {
      await this.api.recordConsent('location_tracking', true, this.locales.current);
      await this.api.createAddress({
        label: input.label || undefined,
        line1: input.line1,
        quarter: input.quarter || undefined,
        city: input.city,
        landmark_note: input.landmarkNote || undefined,
        latitude: input.latitude,
        longitude: input.longitude,
      });
      await this.loadAddresses();
      return true;
    } catch {
      return false;
    }
  }

  /**
   * Load the customer's real jobs (with engagement summary).
   *
   * Read-through the offline cache (P5-02): with no network this resolves to the jobs list from the
   * last successful read instead of the demo fixtures — someone else's fictional plumbing job is a
   * far worse thing to show a customer than their own slightly-old one.
   */
  private async loadJobs(): Promise<void> {
    const { value: jobs } = await this.cache.through('jobs', () => this.api.jobs());
    // Marked answered either way. A failed read with nothing cached is still an answer as far as
    // the screen is concerned — it should show its empty state, not spin forever.
    this.jobsLoaded.set(true);
    if (jobs !== null) {
      this.jobs.set(jobs.map((j) => ({
        id: j.id,
        reference: j.reference,
        title: j.title,
        status: j.status as JobStatus,
        providerName: j.engagement?.provider_name ?? null,
        amountMinor: j.engagement?.agreed_amount_minor ?? j.budget?.amount_minor ?? 0,
        milestonesDone: j.engagement?.milestones_done ?? 0,
        milestonesTotal: j.engagement?.milestones_total ?? 0,
        ...moneyOf(j.engagement),
      })));
    }
  }

  /** The signed-in identity, cached: a name and phone are exactly what should survive a dead network. */
  private async loadMe(): Promise<void> {
    const { value: u } = await this.cache.through('me', () => this.api.me());
    if (u !== null) {
      // `name` is a DISPLAY NAME or nothing.
      //
      // Two ways it used to become the phone number: this line fell back to it, and the server
      // seeds `display_name` WITH the phone for a user created by OTP (there is nothing else to
      // put there — doc 02 is phone-primary and a name is optional). Both made "name" and "phone"
      // the same string, so the account header printed it on both of its lines and initialsOf()
      // was handed a phone number to take initials from. A phone is not a name here.
      const displayName = u.display_name ?? '';
      const name = displayName === u.phone_e164 ? '' : displayName;
      this.me.set({ id: u.id, name, initials: initialsOf(name), phone: u.phone_e164 });
      // This is the first moment the app learns what language the ACCOUNT is in, which is what
      // the server renders bilingual payloads in (P1-05b). See LocaleService::reconcile.
      //
      // The taxonomy refetch is NOT keyed off a before/after comparison here. It used to be, and it
      // broke the moment the account's language began to be adopted at launch as well: whichever of
      // the two got there first, the other saw no change and skipped the refetch, leaving an English
      // category rail — the most visible bilingual payload in the app — above French chrome. The
      // language is now watched directly, so it does not matter who moves it.
      await this.locales.reconcile(u.locale);
    }
  }

  /**
   * Real skill categories drive the discover rail. Cached per locale — the taxonomy is bilingual and
   * changes about once a quarter, so it is the single best thing in the app to serve from disk: a
   * customer with no signal can still browse every trade and compose a request.
   */
  private async loadCategories(): Promise<void> {
    const locale = this.locales.current;
    const { value: skills } = await this.cache.through(
      `skills:${locale}`,
      () => this.api.skills(locale),
    );
    if (skills !== null && skills.length > 0) {
      this.categories.set(skills.map((s) => ({
        id: s.slug,
        label: s.name ?? s.slug,
        icon: CATEGORY_ICONS[s.slug] ?? 'briefcase-outline',
        skillId: s.id,
        leaves: (s.children ?? []).map((c) => ({ id: c.id, label: c.name ?? c.slug })),
      })));
    }
  }

  private readonly providers: Provider[] = [
    { id: 'p1', name: 'Atelier Nkeng', initials: 'AN', skill: 'Plomberie', rating: 4.9, mode: 'onsite', distanceKm: 2.1, verified: true, accent: 'brand' },
    { id: 'p2', name: 'Marie Fotso', initials: 'MF', skill: 'Design graphique', rating: 4.8, mode: 'remote', distanceKm: null, verified: true, accent: 'info' },
    { id: 'p3', name: 'Douala Cool Services', initials: 'DC', skill: 'Climatisation', rating: 4.7, mode: 'onsite', distanceKm: 3.4, verified: false, accent: 'warning' },
    { id: 'p4', name: 'Yaoundé Élec', initials: 'YE', skill: 'Électricité', rating: 4.6, mode: 'onsite', distanceKm: 5.2, verified: true, accent: 'muted' },
    { id: 'p5', name: 'Fresh Design Studio', initials: 'FD', skill: 'Design graphique', rating: 4.5, mode: 'remote', distanceKm: null, verified: false, accent: 'info' },
  ];

  /**
   * The customer's jobs (GET /jobs).
   *
   * Starts EMPTY, for the same reason the addresses list does — and with more at stake. It used to
   * open on five fabricated jobs with real-looking references, provider names and amounts ("Fuite
   * sous l'évier · Atelier Nkeng · 900 000 FCFA"), which meant a customer on a slow network, or one
   * whose fetch failed, was shown four other people's jobs and a sum of money as if they were their
   * own. `loadJobs()` only assigns on a successful read, so a failure left them there indefinitely.
   *
   * Illustrative content is a demo aid; a claim about the user is not. Jobs and money are the
   * strongest claim this app makes about anyone.
   */
  readonly jobs = signal<JobSummary[]>([]);

  /**
   * False until the jobs list has actually been answered — the difference between "still asking"
   * and "you have none", which an empty array alone cannot express. Without it, emptying the
   * fixtures above would flash "no jobs yet" at someone who has four.
   */
  readonly jobsLoaded = signal(false);

  /**
   * The customer's saved addresses (GET /addresses).
   *
   * Starts EMPTY. It used to open with "Domicile — Rue 1.234, Akwa, Douala" and "Bureau —
   * Boulevard de la Liberté, Bonanjo, Douala", which is two lies of a particular kind: they are
   * claims about where the user lives and works, and their ids (`a1`, `a2`) exist nowhere on the
   * server — so picking one on the new-job form produced a job the API would reject. Same rule as
   * the provider identity: illustrative content is a demo aid, a claim about the user is not.
   */
  readonly addresses = signal<SavedAddress[]>([]);

  // The messages tab starts EMPTY and shows its loading state until GET /conversations answers.
  // It used to open on three fabricated conversations — named providers, quoted messages and an
  // unread badge of 2 — which is the same lie the jobs list told, in the one place a person is
  // most likely to believe it: nobody doubts their own inbox. See the note on `jobs`.

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
      conversationId: null,
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

  /**
   * The public provider profile (reviews + display-safe metrics).
   *
   * An unknown id returns a BLANK profile, not a stand-in. This used to fall back to `profiles.p1`,
   * so opening any real provider before their profile loaded — or when the fetch failed — rendered
   * Atelier Nkeng's name, a 4.76 rating from 128 reviews, 94% on-time and three named testimonials,
   * attributed to whoever the customer had actually tapped. Inventing one person's reputation and
   * showing it as another's is the most damaging thing a marketplace can display, and it is exactly
   * what P6-09's shrinkage and P6-12's sample floor exist to prevent on the honest path.
   */
  provider(id: string): ProviderProfile {
    return this.profiles[id] ?? this.blankProfile(id);
  }

  private blankProfile(id: string): ProviderProfile {
    return {
      id,
      name: '',
      initials: '',
      headline: '',
      accent: 'muted',
      verified: false,
      mode: 'onsite',
      city: '',
      ratingAvg: null,
      ratingCount: 0,
      jobsCompleted90d: 0,
      onTimeRate: null,
      responseTime: '',
      memberSince: '',
      skills: [],
      about: '',
      reviews: [],
    };
  }

  /** Providers filtered by the discover mode segment. `both` returns everything. */
  listProviders(mode: EngagementMode | 'both'): Provider[] {
    return mode === 'both' ? this.providers : this.providers.filter((p) => p.mode === mode);
  }

  /**
   * The real discover rail (GET /providers) — public, so it works before sign-in, which is exactly
   * when a customer is deciding whether this marketplace has anyone worth hiring. Cached per
   * (mode, category) so the rail opens populated with no network. Returns null on failure so the
   * caller keeps the demo cards.
   */
  async fetchProviders(mode: EngagementMode | 'both', categoryId?: string): Promise<Provider[] | null> {
    // `hybrid` is a JOB's mode, not a provider's — a provider either travels or doesn't, so it maps
    // onto the on-site pool rather than inventing a third kind of listing.
    const apiMode = mode === 'remote' ? 'remote' : mode === 'both' ? undefined : 'onsite';
    // A category's `id` IS its taxonomy slug (see loadCategories), which is what the endpoint takes.
    const skill = categoryId;
    const locale = this.locales.current;

    const { value } = await this.cache.through(
      `providers:${apiMode ?? 'any'}:${skill ?? 'all'}:${locale}`,
      () => this.api.browseProviders({ skill, mode: apiMode, locale }),
    );
    if (value === null) {
      return null;
    }
    return value.map((p) => this.mapPublicProvider(p));
  }

  /**
   * Search trades by name (P1-07b) — the discover search box.
   *
   * Deliberately a search over the TAXONOMY, not over providers: what a customer types is the thing
   * they need done ("fuite", "AC repair"), and the taxonomy is bilingual and indexed for exactly
   * that. Searching provider headlines would match business names instead, which is not the
   * question being asked. Returns [] on failure — a search box that errors is worse than one that
   * finds nothing.
   */
  async searchSkills(query: string): Promise<{ id: string; slug: string; label: string }[]> {
    const q = query.trim();
    if (q.length < 2) {
      return []; // one letter matches most of the catalogue — not a useful answer
    }

    // `/skills/search` covers LEAF trades only. That is right for the index, but it means a customer
    // typing a perfectly natural word that happens to be one of our CATEGORIES ("plumbing",
    // "plomberie") is told no trade matches — while the app is holding the category list in memory.
    // So categories are matched here, locally: 13 bilingual labels, no request, works offline, and
    // the directory already accepts a category slug and expands it to every leaf beneath.
    const needle = fold(q);
    const categories = this.categories()
      .filter((c) => fold(c.label).includes(needle))
      .map((c) => ({ id: c.id, slug: c.id, label: c.label }));

    let leaves: { id: string; slug: string; label: string }[] = [];
    try {
      const results = await this.api.searchSkills(q, this.locales.current);
      leaves = results.map((s) => ({ id: s.id, slug: s.slug, label: s.name }));
    } catch {
      // Offline or refused — the local category matches are still a useful answer on their own.
    }

    // Categories first: they are the broader, safer pick when a customer is unsure which specific
    // trade their problem falls under.
    return [...categories, ...leaves];
  }

  /** Map one public provider onto a discover card. The API sends no name — the headline stands in. */
  private mapPublicProvider(p: {
    party_id: string; headline?: string | null; verification_tier: number;
    rating_avg?: string | null; serves_onsite?: boolean;
    skills?: { skill_id?: string; name?: string | null }[];
  }): Provider {
    const headline = p.headline ?? '';
    const skill = (p.skills ?? []).map((s) => s.name ?? '').find(Boolean) ?? '';
    return {
      id: p.party_id,
      name: headline || skill,
      initials: headlineInitials(headline || skill),
      skill,
      mode: p.serves_onsite === true ? 'onsite' : 'remote',
      // Never a precise distance: the resource carries no location at all, by design.
      distanceKm: null,
      rating: p.rating_avg !== null && p.rating_avg !== undefined ? Number(p.rating_avg) : 0,
      verified: verifiedTier(p.verification_tier),
      accent: accentFor(p.party_id),
    };
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
      // Demo rows carry no real engagement id, which is what keeps the engagement-scoped actions
      // (complete, review) from offering themselves on a job the server has never heard of.
      engagementId: null, completedAt: null, reviewed: false,
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
      engagementId: null, completedAt: null, reviewed: false,
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
      // A demo row has no real engagement, so none of the engagement-scoped actions offer themselves.
      engagementId: null, completedAt: null, reviewed: false,
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
    const { value: j } = await this.cache.through(`job:${id}`, () => this.api.job(id));
    if (j === null) {
      return null;
    }
    try {
      const eng = j.engagement ?? null;
      const milestones = (eng?.milestones ?? []).map((m) => ({
        id: m.id,
        // A platform-generated title arrives with a key and is said in the reader's language; a
        // title a person wrote arrives without one and is shown exactly as they wrote it.
        title: m.title_key ? this.translate.instant(m.title_key) : m.title,
        amountMinor: m.amount_minor,
        status: mapMilestoneStatus(m.status),
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
        engagementId: eng?.id ?? null,
        completedAt: eng?.completed_at ?? null,
        reviewed: eng?.viewer_has_reviewed ?? false,
      };
    } catch {
      return null;
    }
  }

  /**
   * Mark the engagement finished (P7-02).
   *
   * Not queued for offline like the milestone approval is: completion opens a 14-day review window
   * and schedules nudges to both parties, so sending it late is materially different from sending
   * it now — better to fail honestly and let the person retry than to promise a timer that has not
   * started.
   */
  async completeEngagement(engagementId: string): Promise<boolean> {
    try {
      await this.api.completeEngagement(engagementId);
      return true;
    } catch {
      return false;
    }
  }

  /**
   * The quotations received on a job (P2.5-01). Null when the read fails — the section is then
   * absent rather than empty, because "nobody has quoted" is a different thing to say.
   *
   * Only `submitted` quotes are offered for acceptance. The server sends the others (superseded,
   * rejected, an accepted one) and they are dropped here: a superseded quote is a price that no
   * longer stands, and showing it beside the live one invites accepting the wrong number.
   */
  async fetchQuotes(jobId: string): Promise<JobQuote[] | null> {
    try {
      const rows = await this.api.jobQuotations(jobId);
      const now = Date.now();

      return rows
        .filter((q) => q.status === 'submitted')
        .map((q): JobQuote => {
          const total = q.subtotal.amount_minor;
          const deposit = q.deposit.amount_minor;

          return {
            id: q.id,
            version: q.version,
            status: q.status,
            providerPartyId: q.provider_party_id,
            providerHeadline: q.provider?.headline ?? '',
            providerVerified: (q.provider?.verification_tier ?? 0) >= 2,
            providerRating: q.provider?.rating_avg === null || q.provider?.rating_avg === undefined
              ? null
              : Number(q.provider.rating_avg),
            providerRatingCount: q.provider?.rating_count ?? 0,
            totalMinor: total,
            depositMinor: deposit,
            balanceMinor: Math.max(0, total - deposit),
            notes: q.notes ?? null,
            validUntil: q.valid_until,
            expired: new Date(q.valid_until).getTime() <= now,
            lines: (q.lines ?? []).map((l) => ({
              label: l.label,
              kind: l.kind,
              quantity: Number(l.quantity),
              unitPriceMinor: l.unit_price_minor,
            })),
          };
        });
    } catch {
      return null;
    }
  }

  /**
   * Accept a quotation (P2.5-05) — the engagement forms, the milestone plan is generated and the
   * deposit is captured into escrow.
   *
   * Never queued offline. This one spends the customer's money and creates a commitment to a
   * specific provider at a specific price; a request replayed hours later, against a quote that may
   * have expired or been superseded in the meantime, is not the thing they agreed to. It returns
   * the server's own words on refusal, because a 409 here has a real reason — the quote lapsed,
   * or someone else's quote already formed the engagement — and "something went wrong" would hide
   * the one fact the customer needs.
   */
  async acceptQuote(quotationId: string): Promise<{ ok: boolean; detail?: string }> {
    try {
      await this.api.acceptQuotation(quotationId);
      return { ok: true };
    } catch (e) {
      const problem = e as { detail?: unknown; title?: unknown };
      const detail = [problem.detail, problem.title]
        .find((v): v is string => typeof v === 'string' && v.trim() !== '');

      return { ok: false, detail };
    }
  }

  /**
   * Put money into an engagement's escrow (P3-04).
   *
   * Accepting a quote generates the milestone plan but collects nothing, so before this the job
   * screen could show "In escrow" as a figure the customer had no way to actually pay in. The
   * money moves when they answer the USSD prompt on their own phone — this call only starts it,
   * which is why the screen says to check their handset rather than announcing a payment.
   */
  async fundEscrow(engagementId: string, amountMinor: number, msisdn: string, method?: MobileRail): Promise<{ ok: boolean; detail?: string }> {
    try {
      await this.api.initiatePaymentIntent('escrow', amountMinor, msisdn, engagementId, method);
      return { ok: true };
    } catch (e) {
      const problem = e as { detail?: unknown; title?: unknown };
      const detail = [problem.detail, problem.title]
        .find((v): v is string => typeof v === 'string' && v.trim() !== '');

      return { ok: false, detail };
    }
  }

  /**
   * Rebook a provider (P8-05) — clones the last job with them and sends a direct offer.
   *
   * Offered only where we know the two have actually worked together, so the 422 for "nothing to
   * clone" is not a trap someone falls into: this appears on a finished job, beside the provider
   * who did it.
   */
  async rebook(partyId: string): Promise<{ ok: boolean; jobId?: string; detail?: string }> {
    try {
      const result = await this.api.rebookProvider(partyId);
      return { ok: true, jobId: result.job_id };
    } catch (e) {
      const problem = e as { detail?: unknown; title?: unknown };
      const detail = [problem.detail, problem.title]
        .find((v): v is string => typeof v === 'string' && v.trim() !== '');

      return { ok: false, detail };
    }
  }

  /** This person's own referral code (P8-01), or null when it cannot be read. */
  async referralCode(): Promise<string | null> {
    try {
      const { code } = await this.api.referralCode();
      return code ?? null;
    } catch {
      return null;
    }
  }

  /**
   * Claim somebody else's referral code (P8-01). The refusals are all real and all different — not
   * a code, your own code, already referred — so the server's own sentence is what surfaces.
   */
  async claimReferral(code: string): Promise<{ ok: boolean; detail?: string }> {
    try {
      await this.api.claimReferral(code);
      return { ok: true };
    } catch (e) {
      const problem = e as { detail?: unknown; title?: unknown };
      const detail = [problem.detail, problem.title]
        .find((v): v is string => typeof v === 'string' && v.trim() !== '');

      return { ok: false, detail };
    }
  }

  /**
   * File a warranty claim (P6-11). A claim spawns a real remedy job, so the description is what the
   * returning provider actually works from — which is why the sheet asks for it rather than sending
   * a bare "it broke".
   */
  async claimWarranty(warrantyId: string, description: string): Promise<{ ok: boolean; detail?: string }> {
    try {
      await this.api.fileWarrantyClaim(warrantyId, description);
      return { ok: true };
    } catch (e) {
      const problem = e as { detail?: unknown; title?: unknown };
      const detail = [problem.detail, problem.title]
        .find((v): v is string => typeof v === 'string' && v.trim() !== '');

      return { ok: false, detail };
    }
  }

  /**
   * Raise a dispute on an engagement (P6-06) — the formal route when the thread has stopped being
   * enough. Never queued: someone raising one is usually mid-problem, and a complaint that leaves
   * the phone hours later, silently, is worse than one that failed loudly.
   */
  async raiseDispute(
    engagementId: string,
    category: DisputeCategory,
    body: string,
  ): Promise<{ ok: boolean; detail?: string }> {
    try {
      await this.api.raiseDispute(engagementId, category, body);
      return { ok: true };
    } catch (e) {
      const problem = e as { detail?: unknown; title?: unknown };
      const detail = [problem.detail, problem.title]
        .find((v): v is string => typeof v === 'string' && v.trim() !== '');

      return { ok: false, detail };
    }
  }

  /**
   * The dispute this party has raised on a given engagement, if any (GET /disputes).
   *
   * The endpoint returns every dispute this party has raised, newest first; the screen only cares
   * about the one attached to the job in front of them. Filtering here rather than asking the
   * server keeps it to one request, and the list is a person's own complaints — never long.
   */
  async fetchDispute(engagementId: string): Promise<{ category: string; status: string; resolutionNote: string | null } | null> {
    try {
      const rows = await this.api.disputes();
      const mine = rows.find((d) => d.engagement_id === engagementId);
      return mine === undefined
        ? null
        : { category: mine.category, status: mine.status, resolutionNote: mine.resolution_note ?? null };
    } catch {
      return null;
    }
  }

  /**
   * Refund what is still held in escrow (P3-14).
   *
   * Never queued, and confirmed before it fires. It ends the money side of the engagement: the held
   * balance goes back and nothing further can be released from it. A customer who has already
   * approved a milestone cannot un-approve it, so this only ever moves what is left.
   */
  async refundEscrow(engagementId: string, reason: string): Promise<{ ok: boolean; detail?: string }> {
    try {
      await this.api.refundEngagement(engagementId, reason);
      return { ok: true };
    } catch (e) {
      const problem = e as { detail?: unknown; title?: unknown };
      const detail = [problem.detail, problem.title]
        .find((v): v is string => typeof v === 'string' && v.trim() !== '');

      return { ok: false, detail };
    }
  }

  /**
   * Accept or reject a submitted deliverable (P4-08).
   *
   * Never queued. A rejection sends the provider back to redo work, and an acceptance is what
   * releases the remote path's money — neither should fire hours later from a queue against a
   * deliverable that has since been withdrawn or already reviewed.
   */
  async reviewDeliverable(
    deliverableId: string,
    decision: 'accept' | 'reject',
    rejectReason?: string,
  ): Promise<{ ok: boolean; detail?: string }> {
    try {
      await this.api.reviewDeliverable(deliverableId, decision, rejectReason);
      return { ok: true };
    } catch (e) {
      const problem = e as { detail?: unknown; title?: unknown };
      const detail = [problem.detail, problem.title]
        .find((v): v is string => typeof v === 'string' && v.trim() !== '');

      return { ok: false, detail };
    }
  }

  /**
   * Mint a share link for a live engagement (P6-05) — "someone is coming to my house, here is who
   * and when", for a family member who is not in the app and should not have to be.
   *
   * Never queued. The link is a thing the person is about to send to somebody, so it either exists
   * now or it does not; a share minted an hour later, from a queue, is a link to a visit that has
   * already happened. The raw token comes back exactly once and is not readable again — so a link
   * that is lost is re-minted, never recovered.
   */
  async createShare(engagementId: string): Promise<{ ok: boolean; id?: string; url?: string; expiresAt?: string; detail?: string }> {
    try {
      const share = await this.api.createEngagementShare(engagementId);
      return { ok: true, id: share.id, url: share.url, expiresAt: share.expires_at };
    } catch (e) {
      const problem = e as { detail?: unknown; title?: unknown };
      const detail = [problem.detail, problem.title]
        .find((v): v is string => typeof v === 'string' && v.trim() !== '');

      return { ok: false, detail };
    }
  }

  /** Revoke a share link (P6-05) — the page stops resolving at once, ahead of its own expiry. */
  async revokeShare(shareId: string): Promise<boolean> {
    try {
      await this.api.revokeEngagementShare(shareId);
      return true;
    } catch {
      return false;
    }
  }

  /** Review the other side of a finished engagement (P6-08) — hidden until both have, or 14 days. */
  async submitReview(engagementId: string, rating: number, body: string): Promise<boolean> {
    try {
      await this.api.submitReview(engagementId, { rating, body: body || undefined });
      return true;
    } catch {
      return false;
    }
  }

  /**
   * Approve a milestone — releases its escrow slice (P3-10). Queued when offline, like every other
   * write, but the money is deliberately NOT shown as released until the server says so: the whole
   * point of escrow is that the customer knows exactly what has left it, and an optimistic
   * "released" that later fails would be a lie about their money. A queued approval reads as
   * "we'll send this", and the detail refreshes for real once it lands.
   */
  async approveAndRefresh(jobId: string, milestoneId: string): Promise<{ outcome: WriteOutcome; job: JobDetail | null }> {
    const { outcome } = await this.queue.submit({
      kind: 'milestone_approval',
      method: 'POST',
      path: '/milestones/{milestone}/approve',
      pathParams: { milestone: milestoneId },
    });

    if (outcome === 'sent') {
      const real = await this.fetchJobDetail(jobId);
      if (real !== null) {
        return { outcome, job: real };
      }
      // The approval landed but the re-read didn't — reflect it locally rather than showing a
      // milestone the server has already released as still pending.
      this.approveMilestone(jobId, milestoneId);
      return { outcome, job: this.jobDetail(jobId) };
    }
    // Queued or refused: nothing about the job has changed yet, so the screen keeps what it has.
    return { outcome, job: null };
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
      escrowHeldMinor: 0,
      releasedMinor: 0,
      needsApproval: false,
    };
    this.jobs.update((list) => [job, ...list]);
    return id;
  }

  /**
   * The real messages tab (GET /conversations), cached so it opens populated with no network.
   * Returns null on failure, which the caller shows as an empty inbox rather than as someone
   * else's — there are no demo rows to fall back to any more.
   */
  async fetchChats(): Promise<ChatSummary[] | null> {
    const { value } = await this.cache.through('conversations', () => this.api.conversations());
    if (value === null) {
      return null;
    }
    const chats = value.map((c) => this.mapChat(c));
    this.unreadTotal.set(chats.reduce((sum, c) => sum + c.unread, 0));
    return chats;
  }

  /**
   * Unread messages across every conversation — the pill on the rail's "Chats" item (handoff:
   * Customer Web). Known once the inbox has been read; a stale count is a courtesy, not a claim.
   */
  readonly unreadTotal = signal(0);

  /** Mark a thread read so its badge clears. Best-effort: a failure just leaves the badge up. */
  async markChatRead(conversationId: string): Promise<void> {
    try {
      await this.api.markConversationRead(conversationId);
    } catch {
      // Offline or refused — the count is a courtesy, not something worth surfacing an error for.
    }
  }

  /** Map one API conversation summary onto a chat row. */
  private mapChat(c: {
    id: string; job_id: string; reference?: string | null; title?: string | null;
    counterpart_name?: string | null; unread_count: number;
    last_message?: { kind: string; preview?: string | null; mine?: boolean; created_at: string } | null;
  }): ChatSummary {
    // Before an engagement forms there is no provider to name, so the job's own title is what the
    // customer would recognise — never a placeholder like "Unknown".
    const name = c.counterpart_name ?? c.title ?? '';
    const last = c.last_message ?? null;

    return {
      // The row navigates to the workspace, which is keyed by JOB.
      id: c.job_id,
      conversationId: c.id,
      providerName: name,
      initials: initialsOf(name),
      reference: c.reference ?? '',
      preview: last?.kind === 'text' ? (last.preview ?? '') : '',
      previewKey: last === null || last.kind === 'text' ? undefined : chatPreviewKey(last.kind),
      time: last === null ? '' : shortTime(last.created_at),
      unread: c.unread_count,
      accent: accentFor(c.id),
    };
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
      const [detail, cachedThread] = await Promise.all([
        this.fetchJobDetail(jobId),
        // Cached too: a conversation is the most valuable thing to still be able to READ when the
        // network dies mid-job — the address, the agreed price and what was promised are all in it.
        this.cache.through(`messages:${jobId}`, () => this.api.messages(jobId)),
      ]);
      const thread = cachedThread.value;
      if (detail === null || thread === null) {
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
        conversationId: thread.conversationId,
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

  /**
   * Post a free-form message (P5-02: through the offline queue).
   *
   * A message typed with no signal is not an error — it is the normal case on the networks this
   * product serves. It is persisted with its own idempotency key and replayed when the API is
   * reachable, so it can never arrive twice. Returns the outcome plus the re-fetched thread when
   * the server actually took it; a queued message stays on screen as the caller's optimistic bubble.
   */
  async sendMessage(jobId: string, body: string): Promise<{ outcome: WriteOutcome; thread: WorkspaceThread | null }> {
    const { outcome } = await this.queue.submit({
      kind: 'message',
      method: 'POST',
      path: '/jobs/{job}/messages',
      pathParams: { job: jobId },
      body: { body },
    });
    const thread = outcome === 'sent' ? await this.fetchThread(jobId) : null;
    return { outcome, thread };
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
   * The real public provider profile: their public card, the display-safe metrics (P6-12) and the
   * published double-blind reviews (P6-08).
   *
   * Keyed by PARTY alone. It used to need a job id, because the only public source of a headline was
   * that job's match list — so arriving from anywhere else (the discover rail, a rebook) fell back
   * to demo data. `GET /providers/{party}` removed that dependency: a public profile shouldn't
   * depend on the route the viewer took to reach it. Returns null when the provider is suspended,
   * blocked or unknown (the endpoint 404s), and the caller keeps what it had.
   */
  async fetchProviderProfile(partyId: string): Promise<ProviderProfile | null> {
    const locale = this.locales.current;
    const { value } = await this.cache.through(`provider:${partyId}:${locale}`, async () => {
      const [card, metrics, reviews] = await Promise.all([
        this.api.provider(partyId, locale),
        this.api.providerMetrics(partyId),
        this.api.providerReviews(partyId),
      ]);
      return { card, metrics, reviews };
    });

    if (value === null) {
      return null;
    }
    const { card, metrics, reviews } = value;
    const headline = card.headline ?? '';
    const skills = (card.skills ?? []).map((s) => s.name ?? '').filter(Boolean);

    return {
      id: partyId,
      name: headline || (skills[0] ?? ''),
      initials: headlineInitials(headline || (skills[0] ?? '')),
      headline: skills[0] ?? headline,
      accent: accentFor(partyId),
      verified: verifiedTier(card.verification_tier),
      mode: card.serves_onsite === true ? 'onsite' : 'remote',
      city: '', // the public resource never exposes a location at all (PII)
      ratingAvg: metrics.rating_avg ?? null,
      ratingCount: metrics.rating_count,
      jobsCompleted90d: metrics.jobs_completed_90d,
      onTimeRate: metrics.on_time_rate ?? null,
      responseTime: '', // not a public signal yet — the stat card hides when empty
      memberSince: '', // not exposed publicly — the line hides when empty
      skills,
      about: card.bio ?? '',
      reviews: reviews.map((r) => this.mapReview(r)),
    };
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
