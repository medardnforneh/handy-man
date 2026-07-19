<?php

namespace App\Filament\Resources\JobOffers\Schemas;

use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Schema;

/**
 * Bespoke offer detail (P2-10 rework, full fidelity) — provider + status header, amount/expiry/
 * response metrics, and the offer message, on the shared design tokens.
 */
class JobOfferInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('detail')
                ->view('filament.infolists.offer')
                ->columnSpanFull(),
        ]);
    }
}
