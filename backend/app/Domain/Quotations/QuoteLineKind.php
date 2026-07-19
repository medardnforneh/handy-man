<?php

declare(strict_types=1);

namespace App\Domain\Quotations;

/**
 * The kind of a quotation line (doc 06). Stored as text; kept as an enum so validation and the API
 * contract stay in one place.
 */
enum QuoteLineKind: string
{
    case Labour = 'labour';
    case Material = 'material';
    case Travel = 'travel';
    case Other = 'other';
}
