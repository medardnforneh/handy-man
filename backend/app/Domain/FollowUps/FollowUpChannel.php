<?php

declare(strict_types=1);

namespace App\Domain\FollowUps;

/**
 * A follow-up delivery channel (doc 07). The routing ladder, cheapest/least-intrusive first, is
 * in_app → push → whatsapp → sms → email — WhatsApp is the workhorse for this market.
 */
enum FollowUpChannel: string
{
    case InApp = 'in_app';
    case Push = 'push';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';
    case Email = 'email';
}
