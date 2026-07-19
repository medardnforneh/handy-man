<?php

declare(strict_types=1);

namespace App\Domain\Money;

/**
 * The two sides of a double-entry posting (doc 03). Entry amounts are always positive; the direction
 * carries the sign.
 */
enum EntryDirection: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    public function opposite(): self
    {
        return $this === self::Debit ? self::Credit : self::Debit;
    }
}
