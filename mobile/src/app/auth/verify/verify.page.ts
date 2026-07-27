import { CommonModule } from '@angular/common';
import { Component, DestroyRef, ElementRef, ViewChild, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { AuthService } from '../../core/auth.service';

const RESEND_SECONDS = 30;

/**
 * OTP verify — the second onboarding step (doc 02, OTP-first). The 6-digit code is captured by one
 * hidden input laid over six cells, so the boxed look needs no per-cell auto-advance logic. A wrong
 * code clears and flags; a resend timer throttles re-sends. On success the guard lets the app open.
 * If the user lands here without a pending phone (deep link / reload), we send them back to Welcome.
 */
@Component({
  selector: 'app-verify',
  templateUrl: './verify.page.html',
  styleUrls: ['./verify.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe],
})
export class VerifyPage {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  readonly phone = this.auth.phone;
  readonly code = signal('');
  readonly error = signal(false);
  readonly resendIn = signal(RESEND_SECONDS);

  readonly cells = [0, 1, 2, 3, 4, 5];
  readonly canVerify = computed(() => this.code().length === 6);

  @ViewChild('capture') private captureRef?: ElementRef<HTMLInputElement>;

  constructor() {
    if (this.phone === '') {
      void this.router.navigate(['/welcome']);
      return;
    }
    this.startResendTimer(inject(DestroyRef));
  }

  /** Focus the code field once the page transition has settled, so the user can type immediately. */
  ionViewDidEnter(): void {
    this.captureRef?.nativeElement.focus();
  }

  onCode(value: string | null | undefined): void {
    this.code.set((value ?? '').replace(/\D/g, '').slice(0, 6));
    this.error.set(false);
  }

  charAt(i: number): string {
    return this.code()[i] ?? '';
  }

  async verify(): Promise<void> {
    if (!this.canVerify()) {
      return;
    }
    const ok = await this.auth.verifyOtp(this.code());
    if (ok) {
      void this.router.navigate(['/tabs/discover']);
    } else {
      this.error.set(true);
      this.code.set('');
    }
  }

  async resend(): Promise<void> {
    if (this.resendIn() > 0) {
      return;
    }
    await this.auth.requestOtp(this.phone);
    this.resendIn.set(RESEND_SECONDS);
  }

  private startResendTimer(destroyRef: DestroyRef): void {
    const id = setInterval(() => {
      const next = this.resendIn() - 1;
      this.resendIn.set(next > 0 ? next : 0);
      if (next <= 0) {
        clearInterval(id);
      }
    }, 1000);
    destroyRef.onDestroy(() => clearInterval(id));
  }
}
