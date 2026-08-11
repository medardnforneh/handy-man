<?php

namespace App\Filament\Resources\Reports\Schemas;

use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Schema;

class ReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('detail')
                ->view('filament.infolists.report')
                ->columnSpanFull(),
        ]);
    }
}
