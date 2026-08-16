<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProviderProfiles\Schemas;

use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Schema;

/**
 * What a provider actually looks like: how much work they have finished, how reliably, what their
 * customers said, and whether anything is open against them.
 *
 * The resource had a form and no view at all, so the only way to look at a provider was the screen
 * for CHANGING one — which put a Save button in front of every read, and still showed none of the
 * numbers the judgement rests on.
 */
final class ProviderProfileInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('detail')
                ->view('filament.infolists.provider')
                ->columnSpanFull(),
        ]);
    }
}
