import { Component } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';

interface NavItem {
  readonly tab: string;
  readonly icon: string;
  readonly label: string;
}

/**
 * The customer shell. Both the customer and provider sections stay reachable to every signed-in
 * user — navigation is never role-gated (doc 10); capabilities are checked at the moment of a
 * high-stakes action instead.
 *
 * The same destination list drives the wide-viewport side rail and the phone tab bar, so the two
 * can never drift apart.
 */
@Component({
  selector: 'app-tabs',
  templateUrl: './tabs.page.html',
  styleUrls: ['./tabs.page.scss'],
  imports: [IonicModule, TranslatePipe, RouterLink, RouterLinkActive],
})
export class TabsPage {
  readonly items: readonly NavItem[] = [
    { tab: 'discover', icon: 'compass-outline', label: 'tabs.discover' },
    { tab: 'jobs', icon: 'briefcase-outline', label: 'tabs.jobs' },
    { tab: 'chats', icon: 'chatbubbles-outline', label: 'tabs.chats' },
    { tab: 'account', icon: 'person-outline', label: 'tabs.account' },
  ];
}
