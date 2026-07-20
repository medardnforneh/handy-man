import { CommonModule } from '@angular/common';
import { Component, inject } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { ChatSummary } from '../customer.models';
import { CustomerService } from '../customer.service';

/** Conversation list — one thread per engagement workspace. */
@Component({
  selector: 'app-chats',
  templateUrl: './chats.page.html',
  styleUrls: ['./chats.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe],
})
export class ChatsPage {
  private readonly customers = inject(CustomerService);
  private readonly router = inject(Router);

  readonly chats: ChatSummary[] = this.customers.listChats();

  open(chat: ChatSummary): void {
    void this.router.navigate(['/workspace', chat.id]);
  }
}
