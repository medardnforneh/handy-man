import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { WorkDetail, WorkStatus } from '../provider.models';
import { ProviderService } from '../provider.service';

/**
 * Provider work detail — the execution surface for one job (P5-03/04/06). Check-in shares the
 * provider's arrival (geo + timestamp) and is gated to on-site/hybrid by the engagement mode
 * (EngagementModePolicy) — a remote job exposes no check-in affordance. Structured status signals
 * and the final job report round out "doing the work"; the chat is one tap away.
 */
@Component({
  selector: 'app-provider-work-detail',
  templateUrl: './work-detail.page.html',
  styleUrls: ['./work-detail.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe],
})
export class ProviderWorkDetailPage {
  private readonly provider = inject(ProviderService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly toasts = inject(ToastController);
  private readonly translate = inject(TranslateService);

  private readonly id = this.route.snapshot.paramMap.get('id') ?? '';
  readonly work = signal<WorkDetail>(this.provider.workDetail(this.id));

  /** Check-in is on-site/hybrid only (doc 06); a remote job never offers it. */
  readonly canCheckIn = computed(() => this.work().mode !== 'remote');

  /** The status signals a provider can post; `arrived` is reserved to check-in (P5-06). */
  readonly statuses: WorkStatus[] = ['on_the_way', 'started', 'paused', 'completed'];

  private refresh(): void {
    this.work.set({ ...this.provider.workDetail(this.id) });
  }

  private async toast(key: string): Promise<void> {
    const t = await this.toasts.create({
      message: this.translate.instant(key), duration: 1800, position: 'top', color: 'success',
    });
    await t.present();
  }

  async checkIn(): Promise<void> {
    this.provider.checkIn(this.id);
    this.refresh();
    await this.toast('work.checked_in_toast');
  }

  checkOut(): void {
    this.provider.checkOut(this.id);
    this.refresh();
  }

  setStatus(status: WorkStatus): void {
    this.provider.setWorkStatus(this.id, status);
    this.refresh();
  }

  async submitReport(): Promise<void> {
    this.provider.submitReport(this.id);
    this.refresh();
    await this.toast('work.report_toast');
  }

  openChat(): void {
    void this.router.navigate(['/workspace', this.id]);
  }
}
