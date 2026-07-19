<?php

namespace App\Filament\Resources\Jobs\Schemas;

use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Schema;

/**
 * Bespoke job detail (P2-10 rework, full fidelity) — status header, budget/mode/urgency metrics,
 * customer + location facts, and description, on the shared design tokens.
 */
class JobInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('detail')
                ->view('filament.infolists.job')
                ->columnSpanFull(),
        ]);
    }
}
