import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { EmptyStateComponent } from '../core/ui/empty-state.component';
import { EmergencyContact, SafetyService } from '../core/safety.service';

/** How long the panic button must be held. Long enough not to fire in a pocket, short enough to
 *  be over before you have thought about it. */
const HOLD_MS = 1500;

/**
 * Safety (P6-04) — the panic alert and the people it reaches.
 *
 * The endpoints existed and nothing called them, which on this particular feature is the worst
 * place for that gap to be: this product puts strangers in each other's homes, and the alarm was
 * reachable only through the API.
 *
 * Held, not tapped, and not confirmed. A confirmation dialog in an emergency is an extra thing to
 * read and dismiss with shaking hands; an accidental tap wakes staff and texts family. A hold
 * answers both — nothing fires from a pocket, and nothing stands between the decision and the
 * alert but a second and a half of pressure.
 */
@Component({
  selector: 'app-safety',
  templateUrl: './safety.page.html',
  styleUrls: ['./safety.page.scss'],
  imports: [CommonModule, FormsModule, IonicModule, TranslatePipe, EmptyStateComponent],
})
export class SafetyPage {
  private readonly safety = inject(SafetyService);
  private readonly toasts = inject(ToastController);
  private readonly translate = inject(TranslateService);

  readonly contacts = signal<EmergencyContact[] | null>(null);
  readonly loadFailed = signal(false);

  /** 0 → 1 while the button is held. Drives the ring, so the wait is visible rather than guessed. */
  readonly holdProgress = signal(0);
  /** The comparison lives here, not in the template: `>` inside an attribute also ends a tag to
   *  anything reading the markup with a regex, the bare-string lint included. */
  readonly armed = computed(() => this.holdProgress() > 0);
  readonly raising = signal(false);
  /** Set once an alert has actually been raised in this session — the screen then says so. */
  readonly raised = signal(false);

  readonly sheetOpen = signal(false);
  readonly name = signal('');
  readonly phone = signal('');
  readonly touched = signal(false);
  readonly saving = signal(false);

  readonly nameMissing = computed(() => this.touched() && this.name().trim() === '');
  /** E.164 as the API demands it. Checked here so a typo is a sentence, not a 422. */
  readonly phoneInvalid = computed(() => this.touched() && !/^\+\d{8,15}$/.test(this.phone().trim()));

  private holdTimer: ReturnType<typeof setInterval> | null = null;

  constructor() {
    void this.load();
  }

  async load(): Promise<void> {
    const rows = await this.safety.contacts();
    this.loadFailed.set(rows === null);
    this.contacts.set(rows);
  }

  // --- the panic button ---------------------------------------------------------------------------

  startHold(): void {
    if (this.raising()) {
      return;
    }
    const startedAt = Date.now();
    this.holdTimer = setInterval(() => {
      const progress = Math.min(1, (Date.now() - startedAt) / HOLD_MS);
      this.holdProgress.set(progress);
      if (progress >= 1) {
        this.cancelHold();
        void this.raise();
      }
    }, 50);
  }

  /** Lifting early, leaving the button, or the page going away all abandon the hold. */
  cancelHold(): void {
    if (this.holdTimer !== null) {
      clearInterval(this.holdTimer);
      this.holdTimer = null;
    }
    this.holdProgress.set(0);
  }

  private async raise(): Promise<void> {
    this.raising.set(true);
    const result = await this.safety.panic();
    this.raising.set(false);

    if (result.ok) {
      this.raised.set(true);
    }

    const key = result.ok
      ? (result.located ? 'safety.raised' : 'safety.raised_no_fix')
      : 'safety.raise_failed';

    const toast = await this.toasts.create({
      message: result.detail ?? this.translate.instant(key),
      // Longer than a normal toast: this one is telling someone whether help is coming.
      duration: 6000,
      position: 'top',
      color: result.ok ? 'success' : 'danger',
    });
    await toast.present();
  }

  // --- contacts -----------------------------------------------------------------------------------

  openSheet(): void {
    this.name.set('');
    this.phone.set('+237');
    this.touched.set(false);
    this.sheetOpen.set(true);
  }

  closeSheet(): void {
    this.sheetOpen.set(false);
  }

  async save(): Promise<void> {
    this.touched.set(true);
    if (this.nameMissing() || this.phoneInvalid()) {
      return;
    }
    this.saving.set(true);
    const ok = await this.safety.addContact(this.name().trim(), this.phone().trim());
    this.saving.set(false);

    if (ok) {
      this.sheetOpen.set(false);
      await this.load();
    }
    await this.say(ok ? 'safety.contact_added' : 'safety.contact_failed', ok);
  }

  async remove(contact: EmergencyContact): Promise<void> {
    const ok = await this.safety.removeContact(contact.id);
    if (ok) {
      await this.load();
    }
    await this.say(ok ? 'safety.contact_removed' : 'errors.generic', ok);
  }

  private async say(key: string, ok: boolean): Promise<void> {
    const toast = await this.toasts.create({
      message: this.translate.instant(key),
      duration: ok ? 2500 : 4000,
      position: 'top',
      color: ok ? 'success' : 'danger',
    });
    await toast.present();
  }
}
