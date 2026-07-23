<?php

namespace App\Filament\Resources\VerificationDocuments\Pages;

use App\Filament\Resources\VerificationDocuments\VerificationDocumentResource;
use Filament\Resources\Pages\ListRecords;

class ListVerificationDocuments extends ListRecords
{
    protected static string $resource = VerificationDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return []; // read-only queue: documents arrive via the API, decisions route through the Action
    }
}
