<?php

namespace App\Filament\Resources\ReconciliationExceptions\Pages;

use App\Filament\Resources\ReconciliationExceptions\ReconciliationExceptionResource;
use Filament\Resources\Pages\ListRecords;

class ListReconciliationExceptions extends ListRecords
{
    protected static string $resource = ReconciliationExceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
