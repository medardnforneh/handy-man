import { Pipe, PipeTransform } from '@angular/core';

/**
 * Renders integer minor units as money (CLAUDE.md rule #2 — amounts are ALWAYS integer minor units).
 * XAF has no minor unit in circulation, so scale 0: 900000 → "900 000". The currency label is passed
 * separately by the template so it can sit in its own element.
 */
@Pipe({ name: 'money' })
export class MoneyPipe implements PipeTransform {
  transform(minor: number | null | undefined): string {
    if (minor === null || minor === undefined) {
      return '—';
    }

    // Narrow no-break spaces group the thousands without introducing a locale-specific separator.
    return Math.round(minor)
      .toString()
      .replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  }
}
