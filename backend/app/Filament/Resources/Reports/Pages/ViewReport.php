<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\Actions\ReviewReportAction;
use App\Filament\Resources\Reports\ReportResource;
use Filament\Resources\Pages\ViewRecord;

class ViewReport extends ViewRecord
{
    protected static string $resource = ReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ReviewReportAction::make(),
        ];
    }
}
