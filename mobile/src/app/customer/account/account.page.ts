import { CommonModule } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { Locale, LocaleService, SUPPORTED_LOCALES } from '../../core/locale.service';

type ThemeChoice = 'system' | 'light' | 'dark';

/**
 * Account — language (FR/EN are both first-class, doc 09) and appearance. The theme choice writes
 * `data-theme` on the root, which the generated token stylesheet honours over the system preference.
 */
@Component({
  selector: 'app-account',
  templateUrl: './account.page.html',
  styleUrls: ['./account.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe],
})
export class AccountPage {
  private readonly locales = inject(LocaleService);

  readonly supported = SUPPORTED_LOCALES;
  readonly locale = signal<Locale>(this.locales.current);
  readonly theme = signal<ThemeChoice>('system');

  async setLocale(locale: Locale): Promise<void> {
    await this.locales.choose(locale);
    this.locale.set(locale);
  }

  setTheme(choice: ThemeChoice): void {
    this.theme.set(choice);
    const root = document.documentElement;
    if (choice === 'system') {
      root.removeAttribute('data-theme');
    } else {
      root.setAttribute('data-theme', choice);
    }
  }
}
