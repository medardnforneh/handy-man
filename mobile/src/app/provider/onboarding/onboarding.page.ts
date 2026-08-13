import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { CustomerService } from '../../customer/customer.service';
import { ProviderService } from '../provider.service';

/** How the provider prices this work. `quote_only` is the honest default in this market. */
type PriceModel = 'hourly' | 'fixed' | 'quote_only';

/** Where the provider works — decides whether a service area is asked for at all (doc 06). */
type WorkMode = 'onsite' | 'remote';

/** Service-area radii offered, in kilometres. Small enough to be meaningful in a Cameroonian city. */
const RADII_KM = [5, 10, 20, 50];

/**
 * Becoming a provider.
 *
 * This flow did not exist. "Offer services" led straight into a provider section whose profile,
 * skills and service-area endpoints had been built and shipped in the API (P1-08) and which nothing
 * in the app ever called — so the only providers that could exist were the ones a seeder made. A
 * customer who tapped it landed on a dashboard of zeroes with no way to change that.
 *
 * One screen, not a wizard: three short questions is not a journey, and every extra step is a place
 * to lose someone on a bad connection. Trades are the only required answer, because a provider with
 * no listed trade can never be matched to anything (P2-04) — the rest can be filled in later from
 * the profile tab.
 */
@Component({
  selector: 'app-provider-onboarding',
  templateUrl: './onboarding.page.html',
  styleUrls: ['./onboarding.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe],
})
export class ProviderOnboardingPage {
  private readonly provider = inject(ProviderService);
  private readonly customers = inject(CustomerService);
  private readonly router = inject(Router);
  private readonly toasts = inject(ToastController);
  private readonly translate = inject(TranslateService);

  /** The real bilingual taxonomy (P1-07), already loaded and cached for Discover. */
  readonly categories = this.customers.categories;

  readonly headline = signal('');
  readonly mode = signal<WorkMode>('onsite');
  readonly priceModel = signal<PriceModel>('quote_only');
  readonly radiusKm = signal(10);
  readonly radii = RADII_KM;
  readonly saving = signal(false);

  /** The chosen trades, by skill id. A category with no loaded leaves contributes its own id. */
  readonly selected = signal<Set<string>>(new Set());

  readonly selectedCount = computed(() => this.selected().size);
  readonly canSubmit = computed(() => this.selectedCount() > 0 && !this.saving());

  /** Categories only expose their leaves once the real taxonomy has loaded; fall back to the head. */
  trades(categoryId: string): { id: string; label: string }[] {
    const category = this.categories().find((c) => c.id === categoryId);
    if (category === undefined) {
      return [];
    }
    if (category.leaves && category.leaves.length > 0) {
      return category.leaves;
    }
    return category.skillId ? [{ id: category.skillId, label: category.label }] : [];
  }

  isSelected(skillId: string): boolean {
    return this.selected().has(skillId);
  }

  toggleTrade(skillId: string): void {
    this.selected.update((current) => {
      const next = new Set(current);
      if (next.has(skillId)) {
        next.delete(skillId);
      } else {
        next.add(skillId);
      }
      return next;
    });
  }

  onHeadline(value: string | null | undefined): void {
    this.headline.set(value ?? '');
  }

  setMode(mode: WorkMode): void {
    this.mode.set(mode);
  }

  setPriceModel(model: PriceModel): void {
    this.priceModel.set(model);
  }

  setRadius(km: number): void {
    this.radiusKm.set(km);
  }

  /**
   * Submit, then say what actually happened. A service area can fail on its own — consent refused,
   * no GPS fix — and the profile still exists; telling someone they are set up when their area was
   * never recorded would leave them wondering why no on-site work ever arrives.
   */
  async submit(): Promise<void> {
    if (!this.canSubmit()) {
      return;
    }
    this.saving.set(true);
    try {
      const result = await this.provider.createProfile({
        headline: this.headline().trim(),
        skillIds: [...this.selected()],
        priceModel: this.priceModel(),
        serviceRadiusM: this.mode() === 'onsite' ? this.radiusKm() * 1000 : null,
      });

      const wantedArea = this.mode() === 'onsite';
      const key = wantedArea && !result.serviceArea ? 'pro.setup_done_no_area' : 'pro.setup_done';
      await this.toast(key, wantedArea && !result.serviceArea ? 'warning' : 'success');
      void this.router.navigate(['/pro/home']);
    } catch {
      await this.toast('pro.setup_failed', 'danger');
    } finally {
      this.saving.set(false);
    }
  }

  private async toast(key: string, color: 'success' | 'warning' | 'danger'): Promise<void> {
    const toast = await this.toasts.create({
      message: this.translate.instant(key),
      duration: color === 'success' ? 2500 : 4500,
      position: 'top',
      color,
    });
    await toast.present();
  }
}
