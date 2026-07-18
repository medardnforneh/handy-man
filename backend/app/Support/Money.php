<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Money — the single source of truth for how amounts are represented across the platform.
 *
 * INVARIANTS (see CLAUDE.md "Non-negotiable rules" #1, #2):
 *   - Amounts are ALWAYS integer minor units (bigint). Never float. Never decimal for movement.
 *   - Every amount carries an explicit ISO-4217 currency (char(3), uppercase).
 *   - Arithmetic between different currencies is forbidden and throws.
 *
 * MINOR-UNIT SCALE — documented here, in ONE place, per CLAUDE.md rule #2.
 *   XAF (Central African CFA franc) has no minor unit in circulation, but we still store it at
 *   scale 0 so the column semantics ("*_minor" is an integer count of the smallest unit") stay
 *   uniform across every currency. For XAF, one minor unit == one franc.
 *
 * SIGN CONVENTION — for the double-entry ledger built on top of this (docs/03 §"Sign convention").
 *   Asset and expense accounts increase on DEBIT.
 *   Liability, equity, and revenue accounts increase on CREDIT.
 *   e.g. `provider_payable` is a LIABILITY (we owe the provider) → it increases on credit.
 *   Ledger *entry* amounts are stored strictly positive with a separate direction column; this
 *   value object permits negative amounts because *balances* and *deltas* legitimately go
 *   negative. Guard positivity at the ledger boundary, not here.
 *
 * This class is immutable: every operation returns a new instance.
 */
final readonly class Money
{
    /**
     * Minor-unit scale per currency. The default for anything not listed is 2 (the common case),
     * but every currency we actually transact in MUST be listed explicitly so the choice is never
     * silent.
     *
     * @var array<string, int>
     */
    private const SCALES = [
        'XAF' => 0, // CFA franc BEAC — no minor unit; scale 0.
        'XOF' => 0, // CFA franc BCEAO — same.
        'EUR' => 2,
        'USD' => 2,
    ];

    public function __construct(
        public int $minor,
        public string $currency,
    ) {
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException(
                "Currency must be a 3-letter uppercase ISO-4217 code, got: {$currency}"
            );
        }

        if (! array_key_exists($currency, self::SCALES)) {
            throw new InvalidArgumentException(
                "Unknown currency {$currency}: add its minor-unit scale to Money::SCALES."
            );
        }
    }

    public static function of(int $minor, string $currency): self
    {
        return new self($minor, strtoupper($currency));
    }

    public static function xaf(int $minor): self
    {
        return new self($minor, 'XAF');
    }

    public static function zero(string $currency): self
    {
        return new self(0, strtoupper($currency));
    }

    /**
     * The number of decimal places between the major and minor unit for this currency.
     */
    public function scale(): int
    {
        return self::SCALES[$this->currency];
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    /**
     * Multiply by an integer factor. Kept integer-only on purpose: multiplying money by a float
     * is how rounding bugs enter. For proportional splits use {@see percentage()} or
     * {@see allocate()}, which keep the arithmetic exact.
     */
    public function times(int $factor): self
    {
        return new self($this->minor * $factor, $this->currency);
    }

    public function negated(): self
    {
        return new self(-$this->minor, $this->currency);
    }

    public function absolute(): self
    {
        return new self(abs($this->minor), $this->currency);
    }

    /**
     * A share expressed in basis points (1 bp = 0.01%). e.g. a 15% commission is 1500 bp.
     * Uses round-half-up on the absolute value so the direction of rounding never depends on sign.
     * Exact splits that must sum back to the whole should use {@see allocate()} instead.
     */
    public function percentage(int $basisPoints): self
    {
        if ($basisPoints < 0) {
            throw new InvalidArgumentException('Basis points must be non-negative.');
        }

        $sign = $this->minor < 0 ? -1 : 1;
        $numerator = abs($this->minor) * $basisPoints;
        // Round half up: add half the denominator before integer division.
        $result = intdiv($numerator + 5_000, 10_000);

        return new self($sign * $result, $this->currency);
    }

    /**
     * Split this amount into parts proportional to the given integer ratios, WITHOUT losing or
     * inventing a single minor unit. Remainder units are handed out one at a time to the earliest
     * ratios (Fowler's allocation). Sum of the returned parts always equals the original amount.
     *
     * @param  int  ...$ratios  at least one, each >= 0, not all zero
     * @return array<int, self>
     */
    public function allocate(int ...$ratios): array
    {
        if ($ratios === []) {
            throw new InvalidArgumentException('allocate() needs at least one ratio.');
        }

        $total = array_sum($ratios);
        if ($total <= 0) {
            throw new InvalidArgumentException('allocate() ratios must sum to a positive number.');
        }

        $remainder = $this->minor;
        $parts = [];
        foreach ($ratios as $ratio) {
            $share = intdiv($this->minor * $ratio, $total);
            $parts[] = $share;
            $remainder -= $share;
        }

        // Distribute the leftover minor units (from integer division) one-by-one.
        $i = 0;
        while ($remainder !== 0) {
            $step = $remainder > 0 ? 1 : -1;
            $parts[$i] += $step;
            $remainder -= $step;
            $i = ($i + 1) % count($parts);
        }

        return array_map(fn (int $minor): self => new self($minor, $this->currency), $parts);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minor === $other->minor;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor > $other->minor;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor < $other->minor;
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    /**
     * The API money shape — always `{amount_minor, currency}`, never a float, never a
     * pre-formatted string (see CLAUDE.md "API conventions").
     *
     * @return array{amount_minor: int, currency: string}
     */
    public function toArray(): array
    {
        return [
            'amount_minor' => $this->minor,
            'currency' => $this->currency,
        ];
    }

    /**
     * Human-readable major-unit string for logs and admin surfaces ONLY. Never send this over the
     * API. Uses the currency's documented scale; no locale grouping to keep it lossless.
     */
    public function formatMajor(): string
    {
        $scale = $this->scale();
        if ($scale === 0) {
            return "{$this->minor} {$this->currency}";
        }

        $sign = $this->minor < 0 ? '-' : '';
        $abs = abs($this->minor);
        $divisor = 10 ** $scale;
        $major = intdiv($abs, $divisor);
        $minor = str_pad((string) ($abs % $divisor), $scale, '0', STR_PAD_LEFT);

        return "{$sign}{$major}.{$minor} {$this->currency}";
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot operate on mismatched currencies: {$this->currency} vs {$other->currency}."
            );
        }
    }
}
