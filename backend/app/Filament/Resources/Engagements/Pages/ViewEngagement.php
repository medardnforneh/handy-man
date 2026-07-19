<?php

namespace App\Filament\Resources\Engagements\Pages;

use App\Filament\Resources\Engagements\EngagementResource;
use Filament\Resources\Pages\ViewRecord;

class ViewEngagement extends ViewRecord
{
    protected static string $resource = EngagementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
