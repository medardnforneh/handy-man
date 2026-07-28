import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { EngagementMode } from '../customer.models';
import { CustomerService } from '../customer.service';

/**
 * Post a request — the customer's entry into the marketplace (mirrors CreateJob + PublishJob).
 * Encodes doc 06's conditional-address rule on the client: the address section only appears for
 * on-site/hybrid work and is required there; a remote job carries no address at all. The form can
 * only be posted once the essentials are present, so the server never sees an invalid request.
 */
@Component({
  selector: 'app-new-job',
  templateUrl: './new-job.page.html',
  styleUrls: ['./new-job.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe],
})
export class NewJobPage {
  private readonly customers = inject(CustomerService);
  private readonly router = inject(Router);
  private readonly toasts = inject(ToastController);
  private readonly translate = inject(TranslateService);

  readonly categories = this.customers.categories;
  readonly addresses = this.customers.addresses;

  readonly title = signal('');
  readonly categoryId = signal<string | null>(null);
  readonly skillId = signal<string | null>(null);
  readonly mode = signal<EngagementMode>('onsite');
  readonly addressId = signal<string | null>(null);
  readonly details = signal('');
  readonly budget = signal('');

  /** The leaves (specific services) of the chosen category, when categories came from the API. */
  readonly leaves = computed(() =>
    this.categories().find((c) => c.id === this.categoryId())?.leaves ?? [],
  );

  /** Remote work needs no address (doc 06); on-site/hybrid require one. */
  readonly needsAddress = computed(() => this.mode() !== 'remote');

  readonly canPost = computed(() =>
    this.title().trim().length > 0
    && this.categoryId() !== null
    // If the chosen category exposes real leaves, a specific service must be picked (CreateJob needs a leaf).
    && (this.leaves().length === 0 || this.skillId() !== null)
    && (!this.needsAddress() || this.addressId() !== null),
  );

  selectCategory(id: string): void {
    this.categoryId.set(id);
    this.skillId.set(null); // reset the specific-service choice
  }

  setMode(mode: EngagementMode): void {
    this.mode.set(mode);
    if (!this.needsAddress()) {
      this.addressId.set(null); // clear a stale address when switching to remote
    }
  }

  onTitle(value: string | null | undefined): void {
    this.title.set(value ?? '');
  }

  onDetails(value: string | null | undefined): void {
    this.details.set(value ?? '');
  }

  onBudget(value: string | null | undefined): void {
    this.budget.set((value ?? '').replace(/\D/g, ''));
  }

  async post(): Promise<void> {
    if (!this.canPost()) {
      return;
    }
    const budgetMinor = this.budget() ? Number(this.budget()) : null;
    await this.customers.createJob({
      title: this.title(),
      categoryId: this.categoryId()!,
      skillId: this.skillId(),
      mode: this.mode(),
      addressId: this.needsAddress() ? this.addressId() : null,
      details: this.details(),
      budgetMinor,
    });

    // Land on the list first — the redirect is the important feedback. The toast is an
    // app-level overlay, so it survives the route change and confirms on the Jobs page.
    // (Never `await toast.present()` before navigating: its enter animation can stall and
    // would then block the redirect entirely.)
    await this.router.navigate(['/tabs/jobs']);
    void this.confirmPosted();
  }

  private async confirmPosted(): Promise<void> {
    const toast = await this.toasts.create({
      message: this.translate.instant('newjob.posted'),
      duration: 2000,
      position: 'top',
      color: 'success',
    });
    void toast.present();
  }
}
