<?php

namespace App\Filament\Resources\SafetyAlerts\Pages;

use App\Filament\Resources\SafetyAlerts\SafetyAlertResource;
use Filament\Resources\Pages\ListRecords;

class ListSafetyAlerts extends ListRecords
{
    protected static string $resource = SafetyAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
