import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from './auth.service';

/** Gate the app: an unauthenticated user is sent to Welcome (waits for the persisted flag first). */
export const authGuard: CanActivateFn = async () => {
  const auth = inject(AuthService);
  const router = inject(Router);
  await auth.ensureReady();
  return auth.authed() ? true : router.parseUrl('/welcome');
};

/** The reverse: an already-authenticated user skips onboarding straight into the app. */
export const guestGuard: CanActivateFn = async () => {
  const auth = inject(AuthService);
  const router = inject(Router);
  await auth.ensureReady();
  return auth.authed() ? router.parseUrl('/tabs/discover') : true;
};
