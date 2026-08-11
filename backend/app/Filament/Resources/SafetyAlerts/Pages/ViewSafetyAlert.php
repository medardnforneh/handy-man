<?php

namespace App\Filament\Resources\SafetyAlerts\Pages;

use App\Filament\Resources\SafetyAlerts\Actions\SettleAlertActions;
use App\Filament\Resources\SafetyAlerts\SafetyAlertResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSafetyAlert extends ViewRecord
{
    protected static string $resource = SafetyAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SettleAlertActions::acknowledge(),
            SettleAlertActions::resolve(),
        ];
    }
}
