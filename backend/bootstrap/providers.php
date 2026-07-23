<?php

use App\Providers\AccessServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\MoneyServiceProvider;
use App\Providers\NotificationsServiceProvider;

return [
    AccessServiceProvider::class,
    AppServiceProvider::class,
    AdminPanelProvider::class,
    HorizonServiceProvider::class,
    MoneyServiceProvider::class,
    NotificationsServiceProvider::class,
];
