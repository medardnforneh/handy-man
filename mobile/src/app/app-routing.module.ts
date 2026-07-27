import { NgModule } from '@angular/core';
import { PreloadAllModules, RouterModule, Routes } from '@angular/router';

const routes: Routes = [
  {
    path: 'tabs',
    loadChildren: () => import('./tabs/tabs.routes').then((m) => m.routes),
  },
  {
    // The public provider profile is pushed over the tabs (reviews + metrics + request a quote).
    path: 'provider/:id',
    loadComponent: () => import('./customer/provider/provider.page').then((m) => m.ProviderPage),
  },
  {
    // The engagement workspace is pushed over the tabs (full-screen thread).
    path: 'workspace/:id',
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
