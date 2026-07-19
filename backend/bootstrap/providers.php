<?php

use App\Providers\AccessServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    AccessServiceProvider::class,
    AppServiceProvider::class,
    AdminPanelProvider::class,
    HorizonServiceProvider::class,
];
