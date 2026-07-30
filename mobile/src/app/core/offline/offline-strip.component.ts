import { Component, computed, inject } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { ConnectivityService } from './connectivity.service';
import { WriteQueue } from './write-queue.service';

/**
 * The connectivity strip (P5-02) — one honest line about whether the app can reach the server and
 * what it still owes it.
 *
 * Dropped into a page's `ion-header` under the toolbar, so it is part of the layout rather than an
 * overlay: nothing it can do will ever cover a title, a tab bar or the message composer.
 *
 * It says three different things, because they are three different situations for the user:
 *  - **offline** — what you're looking at is a saved copy, and anything you do will be sent later;
 *  - **catching up** — you're back, and N things are on their way;
 *  - **refused** — the server said no to N things; they will not send themselves, decide.
 * When there is nothing to report it renders nothing at all.
 */
@Component({
  selector: 'app-offline-strip',
  templateUrl: './offline-strip.component.html',
  styleUrls: ['./offline-strip.component.scss'],
  // No Ionic components here by design — see the template note on lazily-loaded icon assets.
  imports: [TranslatePipe],
})
export class OfflineStripComponent {
  private readonly connectivity = inject(ConnectivityService);
  private readonly queue = inject(WriteQueue);

  readonly online = this.connectivity.online;
  readonly pendingCount = computed(() => this.queue.pending().length);
  readonly failedCount = computed(() => this.queue.failed().length);

  /** Back online with a backlog — the one moment worth saying "hold on, they're going out now". */
  readonly catchingUp = computed(() => this.online() && this.pendingCount() > 0);

  /** Try the refused writes again — same idempotency keys, so this still can't duplicate anything. */
  async retryFailed(): Promise<void> {
    for (const entry of [...this.queue.failed()]) {
      await this.queue.retry(entry.id);
    }
  }

  /** Give up on them. Explicit, because it throws away something the user did.  */
  async discardFailed(): Promise<void> {
    for (const entry of [...this.queue.failed()]) {
      await this.queue.discard(entry.id);
    }
  }
}
