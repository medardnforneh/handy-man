import { Routes } from '@angular/router';
import { ProTabsPage } from './pro-tabs.page';

export const routes: Routes = [
  {
    path: '',
    component: ProTabsPage,
    children: [
      {
        path: 'home',
        loadComponent: () => import('../home/home.page').then((m) => m.ProviderHomePage),
      },
      {
        path: 'opportunities',
        loadComponent: () => import('../opportunities/opportunities.page').then((m) => m.ProviderOpportunitiesPage),
      },
      {
        path: 'work',
        loadComponent: () => import('../work/work.page').then((m) => m.ProviderWorkPage),
      },
      {
        path: 'earnings',
        loadComponent: () => import('../earnings/earnings.page').then((m) => m.ProviderEarningsPage),
      },
      {
        path: 'profile',
        loadComponent: () => import('../profile/profile.page').then((m) => m.ProviderProfilePage),
      },
      { path: '', redirectTo: 'home', pathMatch: 'full' },
    ],
  },
];
