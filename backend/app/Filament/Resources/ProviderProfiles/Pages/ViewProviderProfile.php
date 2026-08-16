<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProviderProfiles\Pages;

use App\Filament\Resources\ProviderProfiles\ProviderProfileResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProviderProfile extends ViewRecord
{
    protected static string $resource = ProviderProfileResource::class;

    /** Moderating the headline or bio starts here, so the way to it is on the page that shows them. */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
