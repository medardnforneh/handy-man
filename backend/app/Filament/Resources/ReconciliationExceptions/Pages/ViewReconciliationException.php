<?php

namespace App\Filament\Resources\ReconciliationExceptions\Pages;

use App\Filament\Resources\ReconciliationExceptions\Actions\ResolveExceptionAction;
use App\Filament\Resources\ReconciliationExceptions\ReconciliationExceptionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewReconciliationException extends ViewRecord
{
    protected static string $resource = ReconciliationExceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ResolveExceptionAction::make(),
        ];
    }
}
