<?php

namespace App\Filament\Resources\Parties\Schemas;

use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Schema;

class PartyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('detail')
                ->view('filament.infolists.party')
                ->columnSpanFull(),
        ]);
    }
}
