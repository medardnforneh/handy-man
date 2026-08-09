import { CommonModule } from '@angular/common';
import { Component, DestroyRef, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { EngagementMode, Provider } from '../customer.models';
import { CustomerService } from '../customer.service';

type ModeFilter = EngagementMode | 'both';

/**
 * Discover — the customer's entry point: search, an on-site/remote filter (geography is conditional,
 * doc 06), categories, and nearby providers. Requesting a quote is one tap from here.
 */
@Component({
  selector: 'app-discover',
  templateUrl: './discover.page.html',
  styleUrls: ['./discover.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe],
})
export class DiscoverPage {
  private readonly customers = inject(CustomerService);
  private readonly router = inject(Router);

  /** Signed-in customer's initials for the avatar — real when a session is present (GET /auth/me). */
  readonly userInitials = this.customers.me;

  readonly categories = this.customers.categories;
  /** False = the horizontal rail (default), true = every category laid out as a grid. */
  readonly allCategories = signal(false);
  readonly mode = signal<ModeFilter>('onsite');
  /** The selected trade, or null for the whole pool. */
  readonly category = signal<string | null>(null);
  /** Demo cards until the real rail loads (and when there is no network) — never mixed. */
  readonly providers = signal<Provider[]>(this.customers.listProviders('onsite'));

  /** What the customer typed, and the trades it matched. */
  readonly query = signal('');
  readonly matches = signal<{ id: string; slug: string; label: string }[]>([]);
  readonly searched = signal(false);
  private searchTimer: ReturnType<typeof setTimeout> | null = null;

  constructor() {
    void this.load();
    inject(DestroyRef).onDestroy(() => {
      if (this.searchTimer !== null) {
        clearTimeout(this.searchTimer);
      }
    });
  }

  /**
   * Search as you type, debounced. 250ms is long enough to collapse a burst of keystrokes into one
   * request and short enough that the list feels like it is keeping up — this runs on networks
   * where an extra round trip is expensive.
   */
  onSearch(value: string | null | undefined): void {
    const q = value ?? '';
    this.query.set(q);

    if (this.searchTimer !== null) {
      clearTimeout(this.searchTimer);
    }
    if (q.trim() === '') {
      this.matches.set([]);
      this.searched.set(false);
      return;
    }
    this.searchTimer = setTimeout(() => {
      void this.runSearch(q);
    }, 250);
  }

  private async runSearch(q: string): Promise<void> {
    const results = await this.customers.searchSkills(q);
    // Drop a result set for a query the user has already typed past.
    if (this.query() === q) {
      this.matches.set(results);
      this.searched.set(true);
    }
  }

  /** Pick a matched trade: it becomes the rail's filter, and the search collapses. */
  pickSkill(slug: string): void {
    this.category.set(slug);
    this.query.set('');
    this.matches.set([]);
    this.searched.set(false);
    void this.load();
  }

  setMode(mode: ModeFilter): void {
    this.mode.set(mode);
    // Show the matching fixtures immediately so the segment feels instant, then replace them with
    // the real pool. On a good connection the swap is invisible; on a bad one the screen still moved.
    this.providers.set(this.customers.listProviders(mode));
    void this.load();
  }

  /**
   * Tap a category to narrow the rail to that trade; tap it again to clear. A toggle rather than a
   * one-way selection because the only other way back to everything would be reloading the tab.
   */
  toggleCategory(id: string): void {
    this.category.update((current) => (current === id ? null : id));
    void this.load();
  }

  /**
   * Swap the category rail between a horizontal scroller and a full grid. Purely a layout change —
   * the same categories are already in memory either way, so it costs no request and works offline.
   */
  toggleAllCategories(): void {
    this.allCategories.update((on) => !on);
  }

  /**
   * Load the real rail. This endpoint is public, so it works before sign-in — the one screen where
   * that matters most, since it is what a new customer looks at to decide whether to bother.
   */
  private async load(): Promise<void> {
    const mode = this.mode();
    const category = this.category();
    const real = await this.customers.fetchProviders(mode, category ?? undefined);
    // Ignore a response that lost the race with a faster tap on another segment or category —
    // otherwise a slow request for the previous filter overwrites the list the user is looking at.
    if (real !== null && this.mode() === mode && this.category() === category) {
      this.providers.set(real);
    }
  }

  openProfile(provider: Provider): void {
    // Tapping a provider opens their public profile (reviews + metrics) before requesting a quote.
    void this.router.navigate(['/provider', provider.id]);
  }

  openWorkspace(provider: Provider): void {
    // The quick "Quote" shortcut opens the engagement workspace for the conversation it creates.
    void this.router.navigate(['/workspace', provider.id]);
  }
}
