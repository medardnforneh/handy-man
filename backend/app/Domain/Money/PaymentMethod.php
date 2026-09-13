<?php

declare(strict_types=1);

namespace App\Domain\Money;

/**
 * How money moves between a customer, the platform and a provider — the founder's list, and only
 * this list (decision 2026-09-13): MTN Mobile Money, Orange Money, and cash. No cards, no bank
 * transfer, no wallets; this is the market, and every screen that takes or gives money offers
 * exactly these.
 *
 * The two mobile rails go through the gateway as a collection or a payout with the operator named,
 * so the payer's phone is prompted by the right network rather than a hosted page asking them to
 * choose. Cash never touches the gateway: it is recorded after the fact as a cash settlement
 * (P3-12) and the platform books its commission from it.
 */
enum PaymentMethod: string
{
    case MtnMomo = 'mtn_momo';
    case OrangeMoney = 'orange_money';
    case Cash = 'cash';

    /**
     * The rails a gateway request can name — everything but cash.
     *
     * @return list<self>
     */
    public static function mobileRails(): array
    {
        return [self::MtnMomo, self::OrangeMoney];
    }

    /**
     * The rail a Cameroon number belongs to, from its prefix. The operators' number ranges are
     * public and stable (ART allocations): MTN holds 650–654, 67x and 680–684; Orange holds
     * 655–659, 69x and 685–689. A number outside both is null — the caller must ask.
     *
     * A default, never an oracle: a customer who has ported their number keeps the right to say so,
     * which is why every form shows the choice pre-selected rather than hiding it.
     */
    public static function fromMsisdn(string $msisdn): ?self
    {
        $digits = preg_replace('/\D/', '', $msisdn) ?? '';
        if (str_starts_with($digits, '237')) {
            $digits = substr($digits, 3);
        }
        if (strlen($digits) !== 9 || $digits[0] !== '6') {
            return null;
        }

        $head = (int) substr($digits, 0, 3);

        return match (true) {
            $head >= 650 && $head <= 654, $head >= 670 && $head <= 679, $head >= 680 && $head <= 684 => self::MtnMomo,
            $head >= 655 && $head <= 659, $head >= 690 && $head <= 699, $head >= 685 && $head <= 689 => self::OrangeMoney,
            default => null,
        };
    }

    public function isMobile(): bool
    {
        return $this !== self::Cash;
    }
}
