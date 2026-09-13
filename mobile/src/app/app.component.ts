import { Component, inject } from '@angular/core';
import { Capacitor } from '@capacitor/core';
import { environment } from '../environments/environment';
import { LocaleService } from './core/locale.service';
import { ThemeService } from './core/theme.service';
import { upgradeRequired } from './core/upgrade';

@Component({
  selector: 'app-root',
  templateUrl: 'app.component.html',
  styleUrls: ['app.component.scss'],
  standalone: false,
})
export class AppComponent {
  private readonly locale = inject(LocaleService);
  private readonly theme = inject(ThemeService);

  /** The force-update kill switch (P0-08): set by the transport on the first 426, read here. */
  readonly upgrade = upgradeRequired;
  readonly appVersion = environment.appVersion;
  readonly isNative = Capacitor.isNativePlatform();

  constructor() {
    // Detect + apply the UI language on launch (persisted choice wins; else device locale), then
    // adopt the ACCOUNT's language. Here rather than in a section shell because `/safety` is
    // outside both of them — see LocaleService::adoptAccountLocale.
    void this.locale.init().then(() => this.locale.adoptAccountLocale());
    // Re-apply the persisted light/dark choice so it survives a reload.
    void this.theme.init();
  }

  /**
   * A packaged app updates from its store; the web build updates by reloading, which lets the
   * service worker take the new bundle it has already fetched in the background.
   */
  update(): void {
    if (this.isNative) {
      window.open(environment.storeUrl, '_system');
      return;
    }
    window.location.reload();
  }
}
