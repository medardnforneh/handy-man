import { Component, computed, inject } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { CustomerService } from '../customer/customer.service';

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
 * The same destination list drives the wide-viewport side rail and the phone tab pill, so the two
 * can never drift apart. The rail carries what the handoff's desktop design puts on it: the job
 * count, the unread pill, a "Post a request" door and the account chip.
 */
@Component({
  selector: 'app-tabs',
  templateUrl: './tabs.page.html',
  styleUrls: ['./tabs.page.scss'],
  imports: [IonicModule, TranslatePipe, RouterLink, RouterLinkActive],
})
export class TabsPage {
  private readonly customers = inject(CustomerService);

  readonly items: readonly NavItem[] = [
    { tab: 'discover', icon: 'compass-outline', label: 'tabs.discover' },
    { tab: 'jobs', icon: 'layers-outline', label: 'tabs.jobs' },
    { tab: 'chats', icon: 'chatbubble-ellipses-outline', label: 'tabs.chats' },
    { tab: 'account', icon: 'person-outline', label: 'tabs.account' },
  ];

  readonly me = this.customers.me;
  readonly unread = this.customers.unreadTotal;
  /** Jobs still in flight — what "Jobs" is about, not the archive. */
  readonly jobCount = computed(() => this.customers.jobs().filter((j) => j.status !== 'completed' && j.status !== 'cancelled').length);
}
