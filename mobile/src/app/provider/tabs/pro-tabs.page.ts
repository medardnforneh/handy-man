import { Component, inject } from '@angular/core';
import { Router, RouterLink, RouterLinkActive } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';

interface NavItem {
  readonly tab: string;
  readonly icon: string;
  readonly label: string;
}

/**
 * The provider shell ("Offer services"). Mirrors the customer shell: one destination list drives both
 * the wide-viewport side rail and the phone tab bar, so they never drift. Navigation is never
 * role-gated (doc 10) — the section is reachable to any signed-in user, and "Back to customer" hops
 * to the customer app without a mode switch.
 */
@Component({
  selector: 'app-pro-tabs',
  templateUrl: './pro-tabs.page.html',
  styleUrls: ['./pro-tabs.page.scss'],
  imports: [IonicModule, TranslatePipe, RouterLink, RouterLinkActive],
})
export class ProTabsPage {
  private readonly router = inject(Router);

  readonly items: readonly NavItem[] = [
    { tab: 'home', icon: 'grid-outline', label: 'pro.tab.home' },
    { tab: 'opportunities', icon: 'briefcase-outline', label: 'pro.tab.opportunities' },
    { tab: 'work', icon: 'construct-outline', label: 'pro.tab.work' },
    { tab: 'earnings', icon: 'wallet-outline', label: 'pro.tab.earnings' },
    { tab: 'profile', icon: 'person-outline', label: 'pro.tab.profile' },
  ];

  backToCustomer(): void {
    void this.router.navigate(['/tabs/discover']);
  }
}
