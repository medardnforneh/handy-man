<?php

namespace App\Filament\Resources\ReconciliationExceptions\Schemas;

use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Schema;

class ReconciliationExceptionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('detail')
                ->view('filament.infolists.reconciliation-exception')
                ->columnSpanFull(),
        ]);
    }
}
