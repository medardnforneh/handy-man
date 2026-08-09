import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { ActionSheetController, IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { MoneyPipe } from '../../customer/money.pipe';
import { OfflineStripComponent } from '../../core/offline/offline-strip.component';
import { PipelineEntry, ProviderClient } from '../provider.models';
import { ProviderService } from '../provider.service';

/** Strip accents so "Rene" finds "René" — a phone keyboard rarely reaches for the accent. */
function fold(value: string): string {
  return value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
}

/**
 * The provider's client book (build plan P7-08, doc 07) — the CRM surface whose backend has existed
 * since Phase 7 with no screen in front of it.
 *
 * Everything here is a fact the platform can prove from the provider's own engagements: who they
 * worked for, how often, how much it was worth, and when it last happened. That is the point of the
 * screen — a provider's history is an asset held on the platform rather than a list of numbers in a
 * phone, and it is the reason to come back.
 *
 * Two writes live here, and they pull in opposite directions on purpose:
 *  - a manual re-engagement nudge, which rides the SAME budget + consent gates as every automated
 *    follow-up (P7-03/04) — a provider cannot spam a customer through us; and
 *  - do-not-contact, which is the customer's absolute veto and is honoured with no override.
 */
@Component({
  selector: 'app-provider-clients',
  templateUrl: './clients.page.html',
  styleUrls: ['./clients.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe, MoneyPipe, OfflineStripComponent],
})
export class ProviderClientsPage {
  private readonly provider = inject(ProviderService);
  private readonly toasts = inject(ToastController);
  private readonly sheets = inject(ActionSheetController);
  private readonly translate = inject(TranslateService);

  /**
   * Null while the first read is in flight, and again if it comes back with nothing — the three
   * states (loading / unreachable / genuinely empty) are kept apart because they mean different
   * things to a provider looking at a blank list.
   */
  readonly clients = signal<ProviderClient[] | null>(null);
  readonly loading = signal(true);
  readonly unreachable = signal(false);
  readonly query = signal('');
  /** The party id of a row with a write in flight, so only that row's controls go busy. */
  readonly busy = signal<string | null>(null);

  /** Which half of the CRM surface is showing — the funnel, or the client book. */
  readonly tab = signal<'pipeline' | 'clients'>('pipeline');
  readonly pipeline = signal<PipelineEntry[] | null>(null);

  /** The widest stage, so the bars are drawn to a shared scale rather than each to its own. */
  readonly pipelinePeak = computed(
    () => Math.max(1, ...(this.pipeline() ?? []).map((s) => s.count)),
  );

  /** True when the funnel is genuinely empty — a new provider, not a failed load. */
  readonly pipelineEmpty = computed(
    () => (this.pipeline() ?? []).every((s) => s.count === 0),
  );

  readonly filtered = computed(() => {
    const all = this.clients() ?? [];
    const q = fold(this.query().trim());
    return q === '' ? all : all.filter((c) => fold(c.name).includes(q));
  });

  /** Clients who came back at least once — the number that says whether the book is working. */
  readonly repeatCount = computed(() => (this.clients() ?? []).filter((c) => c.jobCount > 1).length);

  readonly lifetimeTotalMinor = computed(
    () => (this.clients() ?? []).reduce((sum, c) => sum + c.lifetimeValueMinor, 0),
  );

  constructor() {
    void this.load();
  }

  async load(): Promise<void> {
    this.loading.set(true);
    // Both halves in parallel: this screen opens on the funnel, and a provider switching to the
    // client book should not then wait for a second round trip on a slow network.
    const [list, pipeline] = await Promise.all([
      this.provider.fetchClients(),
      this.provider.fetchPipeline(),
    ]);
    this.unreachable.set(list === null && pipeline === null);
    this.clients.set(list ?? []);
    this.pipeline.set(pipeline);
    this.loading.set(false);
  }

  showTab(tab: 'pipeline' | 'clients'): void {
    this.tab.set(tab);
  }

  /** Bar width as a percentage of the widest stage. Floored so a non-zero stage is always visible. */
  barWidth(entry: PipelineEntry): number {
    return entry.count === 0 ? 0 : Math.max(6, Math.round((entry.count / this.pipelinePeak()) * 100));
  }

  /** Pull-to-refresh: a client book is a screen providers re-check rather than open once. */
  async refresh(event: CustomEvent): Promise<void> {
    await this.load();
    await (event.target as HTMLIonRefresherElement).complete();
  }

  onSearch(value: string): void {
    this.query.set(value ?? '');
  }

  /**
   * Send one customer a re-engagement nudge. A refusal here is nearly always a real rule — the
   * daily budget, a withdrawn marketing consent, a do-not-contact — so the server's own words are
   * shown rather than a generic failure. Nothing is queued offline: this is a marketing message and
   * reporting success for something the platform is about to decline would be a lie.
   */
  async followUp(client: ProviderClient): Promise<void> {
    if (client.doNotContact || this.busy() !== null) {
      return;
    }
    this.busy.set(client.partyId);
    const result = await this.provider.sendFollowUp(client.partyId);
    this.busy.set(null);

    await this.toast(
      result.ok ? this.translate.instant('crm.nudge_sent', { name: client.name })
        : result.detail ?? this.translate.instant('crm.nudge_failed'),
      result.ok ? 'success' : 'danger',
    );
  }

  /**
   * The per-client overflow. The sheet IS the confirmation step for do-not-contact: it names the
   * client, states the consequence in its subheader, and carries the destructive role — which is
   * enough deliberation for a control that a mis-tap should not trigger, without stacking a second
   * dialog on top of it.
   */
  async openActions(client: ProviderClient): Promise<void> {
    if (this.busy() !== null) {
      return;
    }
    const blocking = !client.doNotContact;
    const sheet = await this.sheets.create({
      header: client.name,
      subHeader: blocking ? this.translate.instant('crm.dnc_confirm_body', { name: client.name }) : undefined,
      buttons: [
        {
          text: this.translate.instant(blocking ? 'crm.dnc_set_cta' : 'crm.dnc_lift'),
          role: blocking ? 'destructive' : undefined,
          handler: () => { void this.setDoNotContact(client, blocking); },
        },
        { text: this.translate.instant('common.cancel'), role: 'cancel' },
      ],
    });
    await sheet.present();
  }

  private async setDoNotContact(client: ProviderClient, next: boolean): Promise<void> {
    this.busy.set(client.partyId);
    const result = await this.provider.setDoNotContact(client.partyId, next);
    this.busy.set(null);

    if (!result.ok) {
      await this.toast(result.detail ?? this.translate.instant('errors.generic'), 'danger');
      return;
    }

    // Reflect it in place rather than refetching — the row is the only thing that changed, and a
    // full reload would throw away the provider's scroll position and search.
    this.clients.update((list) => (list ?? []).map(
      (c) => (c.partyId === client.partyId ? { ...c, doNotContact: next } : c),
    ));
    await this.toast(
      this.translate.instant(next ? 'crm.dnc_set' : 'crm.dnc_lifted', { name: client.name }),
      next ? 'medium' : 'success',
    );
  }

  private async toast(message: string, color: string): Promise<void> {
    const toast = await this.toasts.create({ message, duration: 2600, position: 'top', color });
    await toast.present();
  }
}
