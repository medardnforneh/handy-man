import { Component } from '@angular/core';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';

/**
 * The customer shell. Both the customer and provider sections stay reachable to every signed-in
 * user — navigation is never role-gated (doc 10); capabilities are checked at the moment of a
 * high-stakes action instead.
 */
@Component({
  selector: 'app-tabs',
  templateUrl: './tabs.page.html',
  styleUrls: ['./tabs.page.scss'],
  imports: [IonicModule, TranslatePipe],
})
export class TabsPage {}
