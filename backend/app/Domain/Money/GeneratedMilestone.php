<?php

declare(strict_types=1);

namespace App\Domain\Money;

/**
 * The milestone titles the PLATFORM generates, as opposed to one a human wrote.
 *
 * These are stored as text on the row, which made them the one thing on the money screen that could
 * not be translated: a French customer accepted a quote and got "Deposit" and "Balance" back. Rule
 * #11 says the server names the THING and the client says it in the reader's language, so the
 * milestone resources emit a key alongside the stored title, and this enum is the single place the
 * two are tied together — the generator writes `Deposit::value`, and the same case supplies the key.
 *
 * A milestone whose title came from a person (a bespoke plan) matches nothing here and keeps its
 * own words, which is the correct outcome: we should never translate what somebody wrote.
 */
enum GeneratedMilestone: string
{
    case Deposit = 'Deposit';
    case Balance = 'Balance';
    case FullPayment = 'Full payment';

    public function key(): string
    {
        return match ($this) {
            self::Deposit => 'milestone.title.deposit',
            self::Balance => 'milestone.title.balance',
            self::FullPayment => 'milestone.title.full_payment',
        };
    }

    /** The i18n key for a stored title, or null when a human wrote it. */
    public static function keyFor(string $title): ?string
    {
        return self::tryFrom($title)?->key();
    }
}
