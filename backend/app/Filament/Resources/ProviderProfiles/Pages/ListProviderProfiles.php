<?php

namespace App\Filament\Resources\ProviderProfiles\Pages;

use App\Filament\Resources\ProviderProfiles\ProviderProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProviderProfiles extends ListRecords
{
    protected static string $resource = ProviderProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
