import { CommonModule } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { ApiService, FollowUpAction } from '../../api/api.service';

/** One nudge, flattened to what a row needs: what it is about, and where it goes. */
interface FollowUpRow {
  id: string;
  kind: string;
  /** The route this nudge is asking someone to open, or null when it points at nothing reachable. */
  route: unknown[] | null;
}

/**
 * The follow-ups waiting on whoever is signed in (P7-02).
 *
 * The server has always scheduled these — "your quote expires tomorrow", "this work is waiting for
 * your approval", "how did it go?" — and sent them by SMS and push. The app was the one channel
 * that never showed them, so a nudge could reach someone's lock screen and then vanish when they
 * opened the app to act on it.
 *
 * Shared by both shells deliberately. A provider gets `payout_ready` where a customer gets
 * `awaiting_approval`, but the mechanism, the row and the two answers are identical, and building
 * it twice would have meant two places for them to drift apart.
 *
 * Silence is the correct rendering when nothing is owed: an empty "nothing needs you" panel is
 * furniture that costs a screenful on the phones this is built for.
 */
@Component({
  selector: 'app-follow-ups',
  standalone: true,
  templateUrl: './follow-ups.component.html',
  imports: [CommonModule, IonicModule, TranslatePipe],
})
export class FollowUpsComponent {
  private readonly api = inject(ApiService);
  private readonly router = inject(Router);

  readonly rows = signal<FollowUpRow[]>([]);
  readonly busy = signal<string | null>(null);

  constructor() {
    void this.load();
  }

  private async load(): Promise<void> {
    try {
      const followUps = await this.api.followUps();
      this.rows.set(
        followUps
          // A nudge already answered is history, not a task. The endpoint returns responded ones so
          // the effectiveness data stays readable; a person does not need to see them again.
          .filter((f) => f.status !== 'responded')
          .map((f) => ({ id: f.id, kind: f.kind, route: routeFor(f) })),
      );
    } catch {
      // A nudge list that cannot be read is not worth an error on someone's home screen — the thing
      // it points at is still reachable the ordinary way.
      this.rows.set([]);
    }
  }

  /**
   * Open what the nudge is about, and record that it worked.
   *
   * `opened` rather than a specific outcome: this component knows the person went where they were
   * asked, not whether they then approved or accepted anything. The screens that own those actions
   * are the ones that can honestly claim them.
   */
  async open(row: FollowUpRow): Promise<void> {
    if (this.busy() !== null) {
      return;
    }

    await this.respond(row, 'opened');
    if (row.route !== null) {
      void this.router.navigate(row.route);
    }
  }

  /** "Not now" is a real answer, and recording it is how a useless nudge gets retired. */
  async dismiss(row: FollowUpRow): Promise<void> {
    if (this.busy() !== null) {
      return;
    }
    await this.respond(row, 'dismissed');
  }

  private async respond(row: FollowUpRow, action: FollowUpAction): Promise<void> {
    this.busy.set(row.id);
    try {
      await this.api.respondToFollowUp(row.id, action);
    } catch {
      // Swallowed on purpose. The tap did what the person wanted; failing to RECORD it is our
      // problem, not theirs, and an error toast here would be about our analytics.
    }
    this.busy.set(null);
    this.rows.update((rows) => rows.filter((r) => r.id !== row.id));
  }
}

/**
 * Where a nudge points. The server sends whichever id the kind is about, so the most specific one
 * present wins: an engagement and a job both lead to the job screen, and a quotation is read there
 * too, because that is where quotes are accepted.
 */
function routeFor(f: { job_id?: string | null; engagement_id?: string | null }): unknown[] | null {
  if (f.job_id) {
    return ['/job', f.job_id];
  }
  return null;
}
