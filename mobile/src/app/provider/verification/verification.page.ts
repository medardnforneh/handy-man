import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { VerificationDocKind } from '../../api/api.service';
import { VerificationDoc } from '../provider.models';
import { ProviderService } from '../provider.service';

/** The three documents that carry a provider to tier 2 — identity, which gates on-site paid work. */
const IDENTITY: VerificationDocKind[] = ['national_id_front', 'national_id_back', 'selfie'];

/** The papers that carry them to tier 3. Any one of them is read toward it; nobody holds all four. */
const TRADE: VerificationDocKind[] = ['trade_license', 'insurance_cert', 'rccm', 'niu'];

/** What the server accepts (SubmitVerificationDocumentRequest), mirrored so a refusal is legible. */
const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
const MAX_BYTES = 10 * 1024 * 1024;

/** One row of the screen: a document kind and where the caller's latest attempt at it stands. */
interface DocRow {
  kind: VerificationDocKind;
  /** The most recent submission of this kind, or null if they have never sent one. */
  doc: VerificationDoc | null;
  status: VerificationDoc['status'] | 'none';
}

/**
 * Verification (P6-01) — sending identity and trade papers in for review.
 *
 * The tier this screen moves is not decorative. A provider below tier 2 cannot accept paid work at
 * a customer's home: the fact model refuses the offer, not the UI (P0-17, P6-03). Until now the
 * profile's "Verify" button did nothing at all, so the gate was unreachable from inside the product
 * — a provider could be told they were not verified and given no way to become verified.
 *
 * Three things this screen deliberately does not do. It does not show the file back: the upload is
 * encrypted into a bucket the app has no read path to, and the only way anyone sees it again is a
 * signed short-TTL URL in the admin panel, logged against the staff member who opened it (P6-02).
 * It does not let the caller pick a tier — the kind fixes the tier, server-side, so mislabelling a
 * selfie as a licence buys nothing. And it does not queue offline: the write queue replays a JSON
 * body, and a 10 MB photo has no business sitting in local storage waiting for a network.
 */
@Component({
  selector: 'app-provider-verification',
  templateUrl: './verification.page.html',
  styleUrls: ['./verification.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe],
})
export class ProviderVerificationPage {
  private readonly provider = inject(ProviderService);
  private readonly toasts = inject(ToastController);
  private readonly translate = inject(TranslateService);

  readonly identity = this.provider.identity;

  /** Everything sent so far, newest first. Null until the first load resolves. */
  private readonly docs = signal<VerificationDoc[] | null>(null);
  readonly loading = signal(true);
  readonly loadFailed = signal(false);

  /** The kind currently uploading — one at a time, so each row can own its own spinner. */
  readonly busy = signal<VerificationDocKind | null>(null);

  /**
   * The two groups, in the order a provider works through them: identity first, because it is the
   * one that unlocks paid work, and the trade papers second, because most providers hold none of
   * them and a screen that opened with RCCM would read as an application to run a company.
   */
  readonly groups = computed(() => [
    {
      key: 'identity',
      title: 'vdoc.group_identity',
      body: 'vdoc.group_identity_body',
      icon: 'document-text-outline',
      rows: this.rows(IDENTITY),
    },
    {
      key: 'trade',
      title: 'vdoc.group_trade',
      body: 'vdoc.group_trade_body',
      icon: 'ribbon-outline',
      rows: this.rows(TRADE),
    },
  ]);

  /** Where the ladder currently stands. Tier 2 is the one that unlocks on-site paid work. */
  readonly tier = computed(() => this.identity().verificationTier);

  /** The three rungs, each knowing whether it has been reached — the comparison belongs here, not
   *  in the template, where `>=` inside an attribute is also the end of a tag to anything reading
   *  the markup with a regex (the bare-string lint among them). */
  readonly steps = computed(() =>
    [1, 2, 3].map((n) => ({ n, key: `vdoc.tier${n}`, reached: this.tier() >= n })),
  );

  readonly accept = ACCEPTED_TYPES.join(',');

  constructor() {
    void this.provider.fetchProfile();
    void this.load();
  }

  async load(): Promise<void> {
    this.loading.set(true);
    const rows = await this.provider.fetchVerificationDocuments();
    this.loading.set(false);
    // A failed load and an empty list are different answers, and the difference decides whether
    // someone sends their ID in a second time. Only the real empty list clears the flag.
    this.loadFailed.set(rows === null);
    this.docs.set(rows);
  }

  /**
   * Build a row per kind, carrying the LATEST submission of that kind.
   *
   * A refused document is re-sent rather than edited, so a kind can have several rows server-side;
   * the newest is the one that describes where the provider actually stands.
   */
  private rows(kinds: VerificationDocKind[]): DocRow[] {
    const all = this.docs() ?? [];
    return kinds.map((kind) => {
      const doc = all.find((d) => d.kind === kind) ?? null;
      return { kind, doc, status: doc?.status ?? 'none' };
    });
  }

  async pick(event: Event, kind: VerificationDocKind): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    // Clear it straight away, or picking the same file after a failure fires no change event.
    input.value = '';
    if (file === null) {
      return;
    }

    // The server's own rules, checked here first. Not a substitute for its validation — a reply
    // that arrives before the upload is simply the honest way to say "this photo is too big".
    if (!ACCEPTED_TYPES.includes(file.type)) {
      await this.say('vdoc.wrong_type', false);
      return;
    }
    if (file.size > MAX_BYTES) {
      await this.say('vdoc.too_big', false);
      return;
    }

    this.busy.set(kind);
    const result = await this.provider.submitVerificationDocument(kind, file);
    this.busy.set(null);

    if (result.ok) {
      await this.load();
      // The tier only moves when a human approves, so the profile is refetched rather than assumed.
      void this.provider.fetchProfile();
    }
    await this.say(result.ok ? 'vdoc.sent' : 'vdoc.send_failed', result.ok, result.detail);
  }

  private async say(key: string, ok: boolean, detail?: string): Promise<void> {
    const toast = await this.toasts.create({
      // The server's own explanation beats our generic copy whenever it sent one.
      message: detail ?? this.translate.instant(key),
      duration: ok ? 2500 : 4000,
      position: 'top',
      color: ok ? 'success' : 'danger',
    });
    await toast.present();
  }
}
