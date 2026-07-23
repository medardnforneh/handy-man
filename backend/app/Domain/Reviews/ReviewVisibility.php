<?php

declare(strict_types=1);

namespace App\Domain\Reviews;

/**
 * A review's visibility in the double-blind flow (doc 02). `pending` = submitted but hidden until the
 * counterparty submits or the window closes; `published` = revealed; `withheld` = removed by an admin
 * (e.g. abusive content) without deleting the record.
 */
enum ReviewVisibility: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Withheld = 'withheld';
}
