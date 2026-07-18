import { Component } from '@angular/core';
import { LocaleService, Locale } from '../core/locale.service';

@Component({
  selector: 'app-home',
  templateUrl: 'home.page.html',
  styleUrls: ['home.page.scss'],
  standalone: false,
})
export class HomePage {
  constructor(private readonly localeService: LocaleService) {}

  get otherLocale(): Locale {
    return this.localeService.current === 'fr' ? 'en' : 'fr';
  }

  toggleLocale(): void {
    void this.localeService.choose(this.otherLocale);
  }
}

