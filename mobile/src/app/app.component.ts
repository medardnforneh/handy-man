import { Component } from '@angular/core';
import { LocaleService } from './core/locale.service';

@Component({
  selector: 'app-root',
  templateUrl: 'app.component.html',
  styleUrls: ['app.component.scss'],
  standalone: false,
})
export class AppComponent {
  constructor(private readonly locale: LocaleService) {
    // Detect + apply the UI language on launch (persisted choice wins; else device locale).
    void this.locale.init();
  }
}
