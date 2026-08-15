import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { OfflineStripComponent } from '../../core/offline/offline-strip.component';
import { EmptyStateComponent } from '../../core/ui/empty-state.component';
import { ChatSummary } from '../customer.models';
import { CustomerService } from '../customer.service';

/**
 * Conversation list — one thread per engagement workspace, searchable, with unread emphasis.
 *
 * Backed by GET /conversations: membership decides what appears, so nothing can be listed here that
 * the user could not then open. The demo rows stand in only until the real list loads (and when
 * there is no session at all), which keeps the screen demoable without ever mixing the two.
 */
@Component({
  selector: 'app-chats',
  templateUrl: './chats.page.html',
  styleUrls: ['./chats.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe, OfflineStripComponent, EmptyStateComponent],
})
export class ChatsPage {
  private readonly customers = inject(CustomerService);
  private readonly router = inject(Router);

  private readonly all = signal<ChatSummary[]>([]);
  readonly query = signal('');
  readonly loaded = signal(false);

  readonly hasChats = computed(() => this.all().length > 0);

  constructor() {
    void this.load();
  }

  /**
   * Re-read on every entry, not just the first: a conversation's unread count and last message
   * change while the user is elsewhere in the app, and a messages tab that shows a stale badge is
   * worse than one that takes a moment.
   */
  ionViewWillEnter(): void {
    void this.load();
  }

  private async load(): Promise<void> {
    const real = await this.customers.fetchChats();
    if (real !== null) {
      this.all.set(real);
    }
    this.loaded.set(true);
  }

  /** Filter by counterpart name or job reference — a fast way back to a specific conversation. */
  readonly chats = computed<ChatSummary[]>(() => {
    const q = this.query().trim().toLowerCase();
    if (!q) {
      return this.all();
    }
    return this.all().filter(
      (c) => c.providerName.toLowerCase().includes(q) || c.reference.toLowerCase().includes(q),
    );
  });

  onSearch(value: string | null | undefined): void {
    this.query.set(value ?? '');
  }

  /** The way out of an empty inbox: there is nothing to say until you have booked someone. */
  findHelp(): void {
    void this.router.navigate(['/tabs/discover']);
  }

  /**
   * Open the thread. The badge is cleared optimistically as well as server-side, because the user
   * has demonstrably seen the conversation — waiting for a round trip to un-bold a row they just
   * tapped would look broken on a slow connection.
   */
  open(chat: ChatSummary): void {
    if (chat.conversationId !== null && chat.unread > 0) {
      this.all.update((rows) => rows.map((r) => (r.id === chat.id ? { ...r, unread: 0 } : r)));
      void this.customers.markChatRead(chat.conversationId);
    }
    void this.router.navigate(['/workspace', chat.id]);
  }
}
