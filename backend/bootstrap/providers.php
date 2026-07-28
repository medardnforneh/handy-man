<?php

use App\Providers\AccessServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\FollowUpsServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\MoneyServiceProvider;
use App\Providers\NotificationsServiceProvider;
use App\Providers\ReferralsServiceProvider;
use App\Providers\WorkspaceServiceProvider;

return [
    AccessServiceProvider::class,
    AppServiceProvider::class,
    AdminPanelProvider::class,
    FollowUpsServiceProvider::class,
    HorizonServiceProvider::class,
    MoneyServiceProvider::class,
    NotificationsServiceProvider::class,
    ReferralsServiceProvider::class,
    WorkspaceServiceProvider::class,
];
