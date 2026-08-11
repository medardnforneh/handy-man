<?php

namespace App\Filament\Resources\Referrals\Pages;

use App\Filament\Resources\Referrals\Actions\ClearReviewAction;
use App\Filament\Resources\Referrals\ReferralResource;
use Filament\Resources\Pages\ViewRecord;

class ViewReferral extends ViewRecord
{
    protected static string $resource = ReferralResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ClearReviewAction::make(),
        ];
    }
}
