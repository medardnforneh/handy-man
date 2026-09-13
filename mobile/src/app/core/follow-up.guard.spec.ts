import { TestBed } from '@angular/core/testing';
import { ActivatedRouteSnapshot, convertToParamMap, Router, RouterStateSnapshot, UrlTree } from '@angular/router';
import { ApiService } from '../api/api.service';
import { followUpGuard } from './follow-up.guard';

/**
 * The route a WhatsApp button or SMS link lands on (doc 07, P7-05). The server's side — the link
 * every nudge carries — is proven in the backend suite; this is the half that has to catch it.
 */
describe('follow-up deep link (/follow-up/:id)', () => {
  let api: jasmine.SpyObj<ApiService>;

  const run = (id: string): Promise<UrlTree> =>
    TestBed.runInInjectionContext(() => {
      const route = { paramMap: convertToParamMap({ id }) } as ActivatedRouteSnapshot;
      return followUpGuard(route, {} as RouterStateSnapshot) as Promise<UrlTree>;
    });

  const path = (tree: UrlTree): string => TestBed.inject(Router).serializeUrl(tree);

  beforeEach(() => {
    api = jasmine.createSpyObj<ApiService>('ApiService', ['followUps', 'respondToFollowUp']);
    api.respondToFollowUp.and.resolveTo({} as never);
    TestBed.configureTestingModule({ providers: [{ provide: ApiService, useValue: api }] });
  });

  it('records the tap as opened and goes to the job the nudge is about', async () => {
    api.followUps.and.resolveTo([{ id: 'fu-1', kind: 'review_request', status: 'sent', job_id: 'job-9' }] as never);

    const tree = await run('fu-1');

    expect(api.respondToFollowUp).toHaveBeenCalledWith('fu-1', 'opened');
    expect(path(tree)).toBe('/job/job-9');
  });

  it('does not overwrite an answer already given — a second tap on an old message is not a second response', async () => {
    api.followUps.and.resolveTo([{ id: 'fu-1', kind: 'review_request', status: 'responded', job_id: 'job-9' }] as never);

    const tree = await run('fu-1');

    expect(api.respondToFollowUp).not.toHaveBeenCalled();
    expect(path(tree)).toBe('/job/job-9');
  });

  it('lands on home when the nudge points at nothing reachable, and when it is not this person\'s', async () => {
    api.followUps.and.resolveTo([{ id: 'fu-2', kind: 'winback', status: 'sent', job_id: null }] as never);

    expect(path(await run('fu-2'))).toBe('/tabs/discover');
    expect(api.respondToFollowUp).toHaveBeenCalledWith('fu-2', 'opened');
    expect(path(await run('someone-elses'))).toBe('/tabs/discover');
  });

  it('still opens the app when the list cannot be read or the tap cannot be recorded', async () => {
    api.followUps.and.rejectWith(new Error('offline'));
    expect(path(await run('fu-1'))).toBe('/tabs/discover');

    api.followUps.and.resolveTo([{ id: 'fu-1', kind: 'review_request', status: 'sent', job_id: 'job-9' }] as never);
    api.respondToFollowUp.and.rejectWith(new Error('500'));
    expect(path(await run('fu-1'))).toBe('/job/job-9');
  });
});
