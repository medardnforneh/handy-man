<?php

namespace App\Filament\Resources\JobOffers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class JobOfferInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('job.reference')->label('Job'),
                TextEntry::make('provider.display_name')->label('Provider'),
                TextEntry::make('origin')->badge(),
                TextEntry::make('status')->badge(),
                TextEntry::make('amount_minor')->label('Amount')->money('XAF', divideBy: 100)->placeholder('—'),
                TextEntry::make('message')->columnSpanFull()->placeholder('—'),
                TextEntry::make('expires_at')->dateTime(),
                TextEntry::make('responded_at')->dateTime()->placeholder('—'),
                TextEntry::make('created_at')->dateTime(),
            ]);
    }
}
