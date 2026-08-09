import { CommonModule } from '@angular/common';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { OfflineStripComponent } from '../../core/offline/offline-strip.component';
import { Deliverable, ReportDraft, ReportMaterial, WorkDetail, WorkStatus } from '../provider.models';
import { MutationResult, ProviderService } from '../provider.service';

/**
 * Provider work detail — the execution surface for one job (P5-03/04/06), on the real endpoints.
 *
 * The screen never decides what is allowed: the server's work-detail read tells it whether check-in
 * exists for this engagement mode (a remote job exposes no check-in affordance, per the
 * EngagementModePolicy), whether a session is open, the last narrated status, and whether the report
 * is in. Every mutation re-reads that state, so the UI can't drift from the server — and a refusal
 * surfaces the server's own `detail` rather than a generic error.
 */
@Component({
  selector: 'app-provider-work-detail',
  templateUrl: './work-detail.page.html',
  styleUrls: ['./work-detail.page.scss'],
  imports: [CommonModule, FormsModule, IonicModule, TranslatePipe, OfflineStripComponent],
})
export class ProviderWorkDetailPage implements OnInit {
  private readonly provider = inject(ProviderService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly toasts = inject(ToastController);
  private readonly translate = inject(TranslateService);

  private readonly id = this.route.snapshot.paramMap.get('id') ?? '';
  readonly work = signal<WorkDetail>(this.provider.workDetail(this.id));

  /** True while a mutation is in flight — the actions disable so a double tap can't double-post. */
  readonly busy = signal(false);

  /** Check-in is on-site/hybrid only (doc 06) — the server decides, we only render its answer. */
  readonly canCheckIn = computed(() => this.work().supportsCheckIn);

  /** The status signals a provider can post; `arrived` is reserved to check-in (P5-06). */
  readonly statuses: WorkStatus[] = ['on_the_way', 'started', 'paused', 'resumed', 'completed'];

  // --- Report composer -------------------------------------------------------------------------

  readonly reportOpen = signal(false);
  readonly summary = signal('');
  readonly materials = signal<ReportMaterial[]>([]);
  readonly extraCharges = signal(0);
  readonly photos = signal<{ file: File; kind: 'before' | 'after' }[]>([]);
  readonly summaryTouched = signal(false);

  readonly summaryMissing = computed(() => this.summaryTouched() && this.summary().trim() === '');

  async ngOnInit(): Promise<void> {
    const real = await this.provider.fetchWorkDetail(this.id);
    if (real) {
      this.work.set(real);
    }
  }

  private async refresh(): Promise<void> {
    const real = await this.provider.fetchWorkDetail(this.id);
    this.work.set(real ?? { ...this.provider.workDetail(this.id) });
  }

  private async toast(
    key: string,
    color: 'success' | 'danger' | 'warning' = 'success',
    message?: string,
  ): Promise<void> {
    const t = await this.toasts.create({
      message: message ?? this.translate.instant(key), duration: 2200, position: 'top', color,
    });
    await t.present();
  }

  /**
   * Run one mutation with the busy guard, then re-read the server's state either way.
   *
   * When the write only reached the offline queue (P5-02) the server has nothing new to tell us, so
   * the `optimistic` patch reflects what the worker just did — otherwise tapping "Check in" in a
   * basement would appear to do nothing at all. That is not a lie: the connectivity strip above says
   * plainly that it is still waiting to send, and the real state replaces this on the next read.
   */
  private async run(
    call: () => Promise<MutationResult>,
    successKey: string,
    optimistic?: (work: WorkDetail) => WorkDetail,
  ): Promise<boolean> {
    if (this.busy()) {
      return false;
    }
    this.busy.set(true);
    try {
      const result = await call();
      await this.refresh();
      if (!result.ok) {
        await this.toast('work.action_failed', 'danger', result.detail);
        return false;
      }
      if (result.queued) {
        if (optimistic !== undefined) {
          this.work.update(optimistic);
        }
        await this.toast('offline.queued_action', 'warning');
        return true;
      }
      await this.toast(successKey);
      return true;
    } finally {
      this.busy.set(false);
    }
  }

  async checkIn(): Promise<void> {
    await this.run(
      () => this.provider.checkIn(this.id),
      'work.checked_in_toast',
      (w) => ({ ...w, checkedIn: true, status: 'arrived' }),
    );
  }

  async checkOut(): Promise<void> {
    await this.run(
      () => this.provider.checkOut(this.id),
      'work.checked_out_toast',
      (w) => ({ ...w, checkedIn: false }),
    );
  }

  async setStatus(status: WorkStatus): Promise<void> {
    await this.run(
      () => this.provider.setWorkStatus(this.id, status),
      'work.status_toast',
      (w) => ({ ...w, status }),
    );
  }

  openReport(): void {
    this.summaryTouched.set(false);
    this.reportOpen.set(true);
  }

  closeReport(): void {
    this.reportOpen.set(false);
  }

  addMaterial(): void {
    this.materials.update((rows) => [...rows, { label: '', qty: 1, unitCostMinor: 0 }]);
  }

  removeMaterial(index: number): void {
    this.materials.update((rows) => rows.filter((_, i) => i !== index));
  }

  /** Write one field of a materials row back, keeping the array immutable for change detection. */
  updateMaterial(index: number, patch: Partial<ReportMaterial>): void {
    this.materials.update((rows) => rows.map((r, i) => (i === index ? { ...r, ...patch } : r)));
  }

  addPhoto(event: Event, kind: 'before' | 'after'): void {
    const input = event.target as HTMLInputElement;
    const chosen = Array.from(input.files ?? []);
    if (chosen.length > 0) {
      this.photos.update((rows) => [...rows, ...chosen.map((file) => ({ file, kind }))]);
    }
    // Clear the input so picking the same file twice still fires a change.
    input.value = '';
  }

  removePhoto(index: number): void {
    this.photos.update((rows) => rows.filter((_, i) => i !== index));
  }

  async submitReport(): Promise<void> {
    this.summaryTouched.set(true);
    if (this.summary().trim() === '') {
      return;
    }
    const draft: ReportDraft = {
      summary: this.summary().trim(),
      // A blank row is the user abandoning a line, not an error — drop them silently.
      materials: this.materials().filter((m) => m.label.trim() !== ''),
      extraChargesMinor: Math.max(0, Math.round(this.extraCharges() || 0)),
      photos: this.photos(),
    };

    const ok = await this.run(() => this.provider.submitReport(this.id, draft), 'work.report_toast');
    if (ok) {
      this.reportOpen.set(false);
      this.summary.set('');
      this.materials.set([]);
      this.extraCharges.set(0);
      this.photos.set([]);
      // Clear the touched flag too, or the now-empty summary re-triggers the error and it flashes
      // over the sheet during the dismiss animation.
      this.summaryTouched.set(false);
    }
  }

  // --- Deliverables (the remote path's proof of work, P4-08) --------------------------------------

  readonly deliverableOpen = signal(false);
  readonly deliverableTitle = signal('');
  readonly deliverableUrl = signal('');
  readonly deliverableTouched = signal(false);

  readonly deliverableTitleMissing = computed(
    () => this.deliverableTouched() && this.deliverableTitle().trim() === '',
  );

  /** The pill tone for a deliverable's review state — accepted reads as done, rejected as a problem. */
  deliverableTone(status: Deliverable['status']): string {
    switch (status) {
      case 'accepted': return 'tone-success';
      case 'rejected': return 'tone-danger';
      default: return 'tone-info';
    }
  }

  openDeliverable(): void {
    this.deliverableTouched.set(false);
    this.deliverableOpen.set(true);
  }

  closeDeliverable(): void {
    this.deliverableOpen.set(false);
  }

  async submitDeliverable(): Promise<void> {
    this.deliverableTouched.set(true);
    const title = this.deliverableTitle().trim();
    if (title === '') {
      return;
    }
    const url = this.deliverableUrl().trim();

    const ok = await this.run(
      () => this.provider.submitDeliverable(this.id, title, url === '' ? undefined : url),
      'work.deliverable_toast',
    );
    if (ok) {
      this.deliverableOpen.set(false);
      this.deliverableTitle.set('');
      this.deliverableUrl.set('');
      this.deliverableTouched.set(false);
    }
  }

  /**
   * The workspace thread is keyed by the JOB (`GET /jobs/{job}/messages`), not the engagement — so
   * this must navigate with `jobId`. Passing the engagement id 404s the read and drops the screen
   * onto the demo thread, which looks like a working chat but is not this job's.
   */
  openChat(): void {
    const jobId = this.work().jobId;
    if (jobId === null) {
      return;
    }
    void this.router.navigate(['/workspace', jobId]);
  }
}
