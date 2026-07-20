import { Routes } from '@angular/router';
import { TabsPage } from './tabs.page';

export const routes: Routes = [
  {
    path: '',
    component: TabsPage,
    children: [
      {
        path: 'discover',
        loadComponent: () => import('../customer/discover/discover.page').then((m) => m.DiscoverPage),
      },
      {
        path: 'jobs',
        loadComponent: () => import('../customer/jobs/jobs.page').then((m) => m.JobsPage),
      },
      {
        path: 'chats',
        loadComponent: () => import('../customer/chats/chats.page').then((m) => m.ChatsPage),
      },
      {
        path: 'account',
        loadComponent: () => import('../customer/account/account.page').then((m) => m.AccountPage),
      },
      { path: '', redirectTo: 'discover', pathMatch: 'full' },
    ],
  },
];
