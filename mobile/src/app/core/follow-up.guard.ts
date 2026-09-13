import { inject } from '@angular/core';
import { CanActivateFn, Router, UrlTree } from '@angular/router';
import { ApiService } from '../api/api.service';

/**
 * Where a nudge's link lands (doc 07, P7-05).
 *
 * Every WhatsApp button and SMS link the server sends points at `/follow-up/{id}` on this host.
 * There is nothing to show at that address — the follow-up is a pointer, not a screen — so this
 * guard is the whole route: it records the tap as `opened` (which is how a channel's effectiveness
 * is measured, and the only reason WhatsApp's cost is ever justified) and then goes where the
 * nudge was asking the person to go. Same rule as the in-app list: the most specific id present
 * wins, and a nudge that points at nothing reachable lands on home, where the list still shows it.
 *
 * Best effort at every step. A tap that cannot be recorded, or a list that cannot be read, still
 * opens the app — the person did what the message asked; the bookkeeping is our problem.
 */
export const followUpGuard: CanActivateFn = async (route): Promise<UrlTree> => {
  const api = inject(ApiService);
  const router = inject(Router);
  const id = route.paramMap.get('id') ?? '';
  const home = router.parseUrl('/tabs/discover');

  let followUps: Awaited<ReturnType<ApiService['followUps']>>;
  try {
    followUps = await api.followUps();
  } catch {
    return home;
  }

  const followUp = followUps.find((f) => f.id === id);
  if (followUp === undefined) {
    return home;
  }

  // Answered once is answered: a second tap on an old message must not overwrite the first record.
  if (followUp.status !== 'responded') {
    try {
      await api.respondToFollowUp(id, 'opened');
    } catch {
      // Swallowed on purpose — see above.
    }
  }

  return followUp.job_id ? router.createUrlTree(['/job', followUp.job_id]) : home;
};
