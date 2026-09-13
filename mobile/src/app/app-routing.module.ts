import { NgModule } from '@angular/core';
import { PreloadAllModules, RouterModule, Routes } from '@angular/router';
import { authGuard, guestGuard } from './core/auth.guard';
import { followUpGuard } from './core/follow-up.guard';

const routes: Routes = [
  {
    // Onboarding front door (phone + OTP). A signed-in user is redirected straight into the app.
    path: 'welcome',
    canActivate: [guestGuard],
    loadComponent: () => import('./auth/welcome/welcome.page').then((m) => m.WelcomePage),
  },
  {
    path: 'verify',
    loadComponent: () => import('./auth/verify/verify.page').then((m) => m.VerifyPage),
  },
  {
    path: 'tabs',
    canActivate: [authGuard],
    loadChildren: () => import('./tabs/tabs.routes').then((m) => m.routes),
  },
  {
    // Post a request — the new-job form, pushed over the tabs.
    path: 'new-job',
    canActivate: [authGuard],
    loadComponent: () => import('./customer/new-job/new-job.page').then((m) => m.NewJobPage),
  },
  {
    // Saving an address (P1-06). Reachable from the account page and from the new-job form, which
    // cannot post an on-site job without one.
    path: 'new-address',
    canActivate: [authGuard],
    loadComponent: () => import('./customer/new-address/new-address.page').then((m) => m.NewAddressPage),
  },
  {
    // The public provider profile is pushed over the tabs (reviews + metrics + request a quote).
    path: 'provider/:id',
    canActivate: [authGuard],
    loadComponent: () => import('./customer/provider/provider.page').then((m) => m.ProviderPage),
  },
  {
    // Job overview (money, milestones, provider, location) — distinct from the chat workspace.
    path: 'job/:id',
    canActivate: [authGuard],
    loadComponent: () => import('./customer/job-detail/job-detail.page').then((m) => m.JobDetailPage),
  },
  {
    // Providers matched to an open job (GET /jobs/{job}/providers), pushed over the tabs.
    path: 'job/:id/providers',
    canActivate: [authGuard],
    loadComponent: () => import('./customer/job-providers/job-providers.page').then((m) => m.JobProvidersPage),
  },
  {
    // Provider section ("Offer services") — its own tab shell (home / opportunities / work / earnings).
    path: 'pro',
    canActivate: [authGuard],
    loadChildren: () => import('./provider/tabs/pro-tabs.routes').then((m) => m.routes),
  },
  {
    // Becoming a provider (P1-08). Outside the `pro` tab shell on purpose: this is the screen you
    // use before that section means anything, and it should not open behind a tab bar advertising
    // four other screens that are all empty until it is finished.
    path: 'become-a-provider',
    canActivate: [authGuard],
    loadComponent: () => import('./provider/onboarding/onboarding.page').then((m) => m.ProviderOnboardingPage),
  },
  {
    // Safety (P6-04): the panic alert and the contacts it reaches. Deliberately outside both the
    // customer and provider shells — a person letting a stranger into their home and a worker
    // walking into an unknown site need the same screen, and it belongs to neither role.
    path: 'safety',
    canActivate: [authGuard],
    loadComponent: () => import('./safety/safety.page').then((m) => m.SafetyPage),
  },
  {
    // The data-subject rights (P1-10): see everything held about you, and have it destroyed.
    // Outside both shells for the same reason as safety — a provider has exactly the same rights
    // as a customer, and putting this under the customer tabs would make it a customer feature.
    path: 'privacy',
    canActivate: [authGuard],
    loadComponent: () => import('./privacy/privacy.page').then((m) => m.PrivacyPage),
  },
  {
    // Sending identity/trade papers in for review (P6-01), pushed over the provider tabs. This is
    // what the profile's "Verify" button had always promised and never opened — and without it the
    // tier-2 gate on on-site paid work could be hit but never cleared from inside the product.
    path: 'verification',
    canActivate: [authGuard],
    loadComponent: () => import('./provider/verification/verification.page').then((m) => m.ProviderVerificationPage),
  },
  {
    // Lead detail + quote composer, pushed over the provider tabs.
    path: 'opportunity/:id',
    canActivate: [authGuard],
    loadComponent: () => import('./provider/lead/lead.page').then((m) => m.ProviderLeadPage),
  },
  {
    // The provider's client book (CRM, P7-08), pushed over the provider tabs. Deliberately not a
    // sixth tab: six labelled tab buttons do not fit a 360px phone without truncating, and this is
    // a screen providers review periodically rather than live in.
    path: 'clients',
    canActivate: [authGuard],
    loadComponent: () => import('./provider/clients/clients.page').then((m) => m.ProviderClientsPage),
  },
  {
    // Provider work detail (check-in, status, report), pushed over the provider tabs.
    path: 'work/:id',
    canActivate: [authGuard],
    loadComponent: () => import('./provider/work-detail/work-detail.page').then((m) => m.ProviderWorkDetailPage),
  },
  {
    // The engagement workspace is pushed over the tabs (full-screen thread).
    path: 'workspace/:id',
    canActivate: [authGuard],
    loadComponent: () => import('./customer/workspace/workspace.page').then((m) => m.WorkspacePage),
  },
  {
    // Where a WhatsApp button or SMS link lands (doc 07). Not a screen: the guard records the tap
    // and redirects to whatever the nudge was about, so this route never renders anything.
    path: 'follow-up/:id',
    canActivate: [authGuard, followUpGuard],
    children: [],
  },
  {
    path: 'home',
    loadChildren: () => import('./home/home.module').then((m) => m.HomePageModule),
  },
  {
    path: '',
    redirectTo: 'tabs/discover',
    pathMatch: 'full',
  },
];

@NgModule({
  imports: [RouterModule.forRoot(routes, { preloadingStrategy: PreloadAllModules })],
  exports: [RouterModule],
})
export class AppRoutingModule {}
