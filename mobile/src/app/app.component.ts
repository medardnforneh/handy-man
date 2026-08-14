import { Component, inject } from '@angular/core';
import { LocaleService } from './core/locale.service';
import { ThemeService } from './core/theme.service';

@Component({
  selector: 'app-root',
  templateUrl: 'app.component.html',
  styleUrls: ['app.component.scss'],
  standalone: false,
})
export class AppComponent {
  private readonly locale = inject(LocaleService);
  private readonly theme = inject(ThemeService);

  constructor() {
    // Detect + apply the UI language on launch (persisted choice wins; else device locale), then
    // adopt the ACCOUNT's language. Here rather than in a section shell because `/safety` is
    // outside both of them — see LocaleService::adoptAccountLocale.
    void this.locale.init().then(() => this.locale.adoptAccountLocale());
    // Re-apply the persisted light/dark choice so it survives a reload.
    void this.theme.init();
  }
}
