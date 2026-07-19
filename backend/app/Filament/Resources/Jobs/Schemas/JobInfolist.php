<?php

namespace App\Filament\Resources\Jobs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class JobInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('reference'),
                TextEntry::make('title'),
                TextEntry::make('customer.display_name')->label('Customer'),
                TextEntry::make('skill.name_fr')->label('Skill'),
                TextEntry::make('engagement_mode')->badge(),
                TextEntry::make('assignment_mode')->badge(),
                TextEntry::make('status')->badge(),
                TextEntry::make('price_model'),
                TextEntry::make('urgency'),
                TextEntry::make('description')->columnSpanFull()->placeholder('—'),
                TextEntry::make('published_at')->dateTime()->placeholder('—'),
                TextEntry::make('cancelled_at')->dateTime()->placeholder('—'),
                TextEntry::make('created_at')->dateTime(),
            ]);
    }
}
