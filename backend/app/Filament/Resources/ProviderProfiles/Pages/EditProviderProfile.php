<?php

namespace App\Filament\Resources\ProviderProfiles\Pages;

use App\Filament\Resources\ProviderProfiles\ProviderProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProviderProfile extends EditRecord
{
    protected static string $resource = ProviderProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
