<?php

namespace App\Filament\Resources\Disputes\Schemas;

use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Schema;

/**
 * The dispute detail is a bespoke view, in the same visual language as the dashboard and the
 * engagement page. It exists because the queue truncates the complaint to 60 characters, and an
 * adjudication made without reading the complaint in full is not an adjudication.
 */
class DisputeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('detail')
                ->view('filament.infolists.dispute')
                ->columnSpanFull(),
        ]);
    }
}
