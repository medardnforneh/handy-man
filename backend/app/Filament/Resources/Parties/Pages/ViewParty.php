<?php

namespace App\Filament\Resources\Parties\Pages;

use App\Filament\Resources\Parties\Actions\AccountStatusActions;
use App\Filament\Resources\Parties\PartyResource;
use Filament\Resources\Pages\ViewRecord;

class ViewParty extends ViewRecord
{
    protected static string $resource = PartyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AccountStatusActions::suspend(),
            AccountStatusActions::reinstate(),
        ];
    }
}
