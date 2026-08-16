<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Schema;

/**
 * How this customer has actually behaved — the counterpart of the provider stats page.
 *
 * This began by rendering the Parties blade, on the reasoning that a party is never "a customer" or
 * "a provider" in this product (doc 10) and one screen should show both halves. That was right about
 * identity and wrong about work: the party screen answers "who is this", and staff opening a
 * customer are asking "how have they been" — do they hire the providers they summon, do they pay,
 * and do they fight. None of which a count of jobs posted can answer.
 *
 * The two links at the foot keep what the shared screen gave for free: the provider half when the
 * same account is both, and the full identity record with its consents.
 */
final class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('detail')
                ->view('filament.infolists.customer')
                ->columnSpanFull(),
        ]);
    }
}
