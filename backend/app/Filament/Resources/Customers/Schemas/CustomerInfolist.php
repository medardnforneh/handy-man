<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Schema;

/**
 * The same detail screen the Parties resource renders, deliberately.
 *
 * That blade already answers the question this one would: it shows a party's activity as customer
 * AND as provider, side by side, because "a party is never 'a customer' or 'a provider' in this
 * product (doc 10)". Writing a customer-only version would mean two screens about one person, drifting
 * apart every time either is touched — and it would hide the provider half from staff who arrived
 * from this list, which is exactly the half they need when the same account is both.
 *
 * What Customers adds over Parties is the LIST: who these people are, what they have spent, and
 * which of them have a dispute open. The detail of one of them is just the party.
 */
final class CustomerInfolist
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
