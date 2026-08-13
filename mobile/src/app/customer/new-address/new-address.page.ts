import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { currentPosition } from '../../core/geolocation';
import { CustomerService } from '../customer.service';

/**
 * Saving an address.
 *
 * This did not exist, and its absence was not cosmetic: an on-site or hybrid job requires an
 * `address_id` — a DB CHECK, not just validation (P2-01) — and the new-job form could only pick
 * from a list nothing in the app could add to. Two fixture addresses made the picker look
 * populated, so the hole was invisible until you tried to post a real job with a real session and
 * the ids (`a1`, `a2`) turned out to exist nowhere on the server.
 *
 * Coordinates come from the device, deliberately. `addresses.point` is a `geography(Point,4326)`
 * behind a GIST index and provider matching is an ST_DWithin against it (P1-06/P2-04), so a typed
 * street with no point is an address no provider can be matched to. There is no geocoder in this
 * product and no map (P2-03 keeps provider geography private), so the honest way to get a real
 * point is to ask the device for one — and to say clearly that we cannot save the address without
 * it, rather than storing a plausible-looking centre-of-city guess.
 */
@Component({
  selector: 'app-new-address',
  templateUrl: './new-address.page.html',
  styleUrls: ['./new-address.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe],
})
export class NewAddressPage {
  private readonly customers = inject(CustomerService);
  private readonly router = inject(Router);
  private readonly toasts = inject(ToastController);
  private readonly translate = inject(TranslateService);

  readonly label = signal('');
  readonly line1 = signal('');
  readonly quarter = signal('');
  readonly city = signal('');
  readonly landmark = signal('');

  /** The device's fix. Null until asked for; `locating` while the browser is deciding. */
  readonly fix = signal<{ latitude: number; longitude: number } | null>(null);
  readonly locating = signal(false);
  readonly locationRefused = signal(false);
  readonly saving = signal(false);

  readonly canSave = computed(
    () => this.line1().trim() !== '' && this.city().trim() !== '' && this.fix() !== null && !this.saving(),
  );

  onLabel(v: string | null | undefined): void { this.label.set(v ?? ''); }
  onLine1(v: string | null | undefined): void { this.line1.set(v ?? ''); }
  onQuarter(v: string | null | undefined): void { this.quarter.set(v ?? ''); }
  onCity(v: string | null | undefined): void { this.city.set(v ?? ''); }
  onLandmark(v: string | null | undefined): void { this.landmark.set(v ?? ''); }

  /** Ask the device where we are. A refusal is a state with a retry, not an error toast. */
  async locate(): Promise<void> {
    this.locating.set(true);
    this.locationRefused.set(false);
    const fix = await currentPosition();
    this.locating.set(false);
    if (fix === null) {
      this.locationRefused.set(true);
      return;
    }
    this.fix.set({ latitude: fix.latitude, longitude: fix.longitude });
  }

  async save(): Promise<void> {
    const fix = this.fix();
    if (!this.canSave() || fix === null) {
      return;
    }
    this.saving.set(true);
    const ok = await this.customers.createAddress({
      label: this.label().trim(),
      line1: this.line1().trim(),
      quarter: this.quarter().trim(),
      city: this.city().trim(),
      landmarkNote: this.landmark().trim(),
      latitude: fix.latitude,
      longitude: fix.longitude,
    });
    this.saving.set(false);

    const toast = await this.toasts.create({
      message: this.translate.instant(ok ? 'address.saved' : 'address.save_failed'),
      duration: ok ? 2000 : 4000,
      position: 'top',
      color: ok ? 'success' : 'danger',
    });
    await toast.present();

    if (ok) {
      this.router.navigateByUrl(this.returnTo);
    }
  }

  /** Where to go back to — the new-job form when we came from it, otherwise the account page. */
  private get returnTo(): string {
    return history.state?.returnTo ?? '/tabs/account';
  }
}
