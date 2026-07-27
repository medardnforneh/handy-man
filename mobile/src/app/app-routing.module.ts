import { NgModule } from '@angular/core';
import { PreloadAllModules, RouterModule, Routes } from '@angular/router';
import { authGuard, guestGuard } from './core/auth.guard';

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
    // The public provider profile is pushed over the tabs (reviews + metrics + request a quote).
    path: 'provider/:id',
    canActivate: [authGuard],
    loadComponent: () => import('./customer/provider/provider.page').then((m) => m.ProviderPage),
  },
  {
    // The engagement workspace is pushed over the tabs (full-screen thread).
    path: 'workspace/:id',
    canActivate: [authGuard],
    loadComponent: () => import('./customer/workspace/workspace.page').then((m) => m.WorkspacePage),
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
