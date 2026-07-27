import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { ChatSummary } from '../customer.models';
import { CustomerService } from '../customer.service';

/** Conversation list — one thread per engagement workspace, searchable, with unread emphasis. */
@Component({
  selector: 'app-chats',
  templateUrl: './chats.page.html',
  styleUrls: ['./chats.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe],
})
export class ChatsPage {
  private readonly customers = inject(CustomerService);
  private readonly router = inject(Router);

  private readonly all: ChatSummary[] = this.customers.listChats();
  readonly query = signal('');
  readonly hasChats = this.all.length > 0;

  /** Filter by provider name or job reference — a fast way back to a specific conversation. */
  readonly chats = computed<ChatSummary[]>(() => {
    const q = this.query().trim().toLowerCase();
    if (!q) {
      return this.all;
    }
    return this.all.filter(
      (c) => c.providerName.toLowerCase().includes(q) || c.reference.toLowerCase().includes(q),
    );
  });

  onSearch(value: string | null | undefined): void {
    this.query.set(value ?? '');
  }

  open(chat: ChatSummary): void {
    void this.router.navigate(['/workspace', chat.id]);
  }
}
