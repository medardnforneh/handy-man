<?php

namespace App\Filament\Resources\Engagements\Schemas;

use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Schema;

/**
 * The engagement detail is a bespoke view (P2-10 rework, full fidelity) — a polished money +
 * milestones + workforce summary rendered on the design tokens, matching the dashboard's language.
 */
class EngagementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('detail')
                ->view('filament.infolists.engagement')
                ->columnSpanFull(),
        ]);
    }
}
