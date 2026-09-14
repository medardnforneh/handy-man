import { Injectable, signal } from '@angular/core';

/**
 * Which shape the app is wearing (handoff: Customer Web). Below `md` the rail collapses and the
 * tab pill is the navigation; from `lg` the screens that have a desktop layout — Home's two cards
 * and jobs table, the three-pane workspace — switch to it. One signal, read by templates, so the
 * threshold lives in exactly one place and a resize moves every screen together.
 *
 * `matchMedia` rather than a resize listener: it fires once, on the crossing, and the browser
 * does the arithmetic.
 */
@Injectable({ providedIn: 'root' })
export class ViewportService {
  /** The desktop layouts: the handoff's rail collapses to icons below ~1100px; 1024 is the nearest breakpoint. */
  readonly wide = signal(false);

  constructor() {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
      return;
    }
    const query = window.matchMedia('(min-width: 1024px)');
    this.wide.set(query.matches);
    query.addEventListener('change', (e) => this.wide.set(e.matches));
  }
}
