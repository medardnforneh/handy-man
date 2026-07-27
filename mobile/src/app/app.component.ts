import { Component } from '@angular/core';
import { LocaleService } from './core/locale.service';
import { ThemeService } from './core/theme.service';

@Component({
  selector: 'app-root',
  templateUrl: 'app.component.html',
  styleUrls: ['app.component.scss'],
  standalone: false,
})
export class AppComponent {
  constructor(
    private readonly locale: LocaleService,
    private readonly theme: ThemeService,
  ) {
    // Detect + apply the UI language on launch (persisted choice wins; else device locale).
    void this.locale.init();
    // Re-apply the persisted light/dark choice so it survives a reload.
    void this.theme.init();
  }
}
