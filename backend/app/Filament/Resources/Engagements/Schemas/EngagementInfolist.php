<?php

namespace App\Filament\Resources\Engagements\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class EngagementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('job.reference')->label('Job'),
                TextEntry::make('provider.display_name')->label('Provider'),
                TextEntry::make('agreed_amount_minor')->label('Agreed amount')->money('XAF', divideBy: 100),
                TextEntry::make('platform_fee_minor')->label('Platform fee')->money('XAF', divideBy: 100),
                TextEntry::make('is_escrowed')->badge()->label('Escrowed'),
                TextEntry::make('accepted_at')->dateTime(),
                TextEntry::make('completed_at')->dateTime()->placeholder('—'),
            ]);
    }
}
