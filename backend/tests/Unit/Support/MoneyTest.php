<?php

declare(strict_types=1);

use App\Support\Money;

it('constructs from minor units with an explicit currency', function () {
    $m = Money::xaf(20_000);

    expect($m->minor)->toBe(20_000)
        ->and($m->currency)->toBe('XAF')
        ->and($m->scale())->toBe(0);
});

it('rejects a malformed currency code', function () {
    Money::of(100, 'xa');
})->throws(InvalidArgumentException::class);

it('rejects an unknown currency with no documented scale', function () {
    Money::of(100, 'ZZZ');
})->throws(InvalidArgumentException::class);

it('adds and subtracts within the same currency', function () {
    expect(Money::xaf(1_500)->plus(Money::xaf(500))->minor)->toBe(2_000)
        ->and(Money::xaf(1_500)->minus(Money::xaf(500))->minor)->toBe(1_000);
});

it('refuses arithmetic across currencies', function () {
    Money::of(100, 'EUR')->plus(Money::of(100, 'USD'));
})->throws(InvalidArgumentException::class);

it('permits negative amounts for balances and deltas', function () {
    $balance = Money::xaf(1_000)->minus(Money::xaf(2_500));

    expect($balance->minor)->toBe(-1_500)
        ->and($balance->isNegative())->toBeTrue()
        ->and($balance->absolute()->minor)->toBe(1_500);
});

it('multiplies by an integer factor only', function () {
    expect(Money::xaf(750)->times(3)->minor)->toBe(2_250);
});

it('computes a percentage in basis points with round-half-up', function () {
    // 15% commission on 20,000 XAF = 3,000 exactly.
    expect(Money::xaf(20_000)->percentage(1_500)->minor)->toBe(3_000);
    // 33.33% of 100 = 33.33 → rounds to 33; 50% of 101 = 50.5 → rounds up to 51.
    expect(Money::xaf(100)->percentage(3_333)->minor)->toBe(33)
        ->and(Money::xaf(101)->percentage(5_000)->minor)->toBe(51);
});

it('rounds percentages symmetrically regardless of sign', function () {
    expect(Money::xaf(-101)->percentage(5_000)->minor)->toBe(-51);
});

it('allocates without losing or inventing minor units', function () {
    // Classic case: 100 split 1:1:1 cannot be 33.33 each; the leftover unit goes to the first.
    $parts = Money::xaf(100)->allocate(1, 1, 1);

    $minors = array_map(fn (Money $p) => $p->minor, $parts);

    expect($minors)->toBe([34, 33, 33])
        ->and(array_sum($minors))->toBe(100);
});

it('allocation always sums back to the original for arbitrary ratios', function () {
    $original = Money::xaf(9_999);

    $parts = $original->allocate(70, 20, 10); // e.g. provider / platform / referrer

    $sum = array_reduce($parts, fn (int $c, Money $p) => $c + $p->minor, 0);

    expect($sum)->toBe(9_999);
});

it('exposes the API money shape, never a float or formatted string', function () {
    expect(Money::xaf(20_000)->toArray())->toBe([
        'amount_minor' => 20_000,
        'currency' => 'XAF',
    ]);
});

it('formats major units using the documented per-currency scale', function () {
    expect(Money::xaf(20_000)->formatMajor())->toBe('20000 XAF')       // scale 0
        ->and(Money::of(2_050, 'EUR')->formatMajor())->toBe('20.50 EUR') // scale 2
        ->and(Money::of(-2_005, 'EUR')->formatMajor())->toBe('-20.05 EUR');
});

it('compares amounts within a currency', function () {
    expect(Money::xaf(200)->greaterThan(Money::xaf(100)))->toBeTrue()
        ->and(Money::xaf(100)->lessThan(Money::xaf(200)))->toBeTrue()
        ->and(Money::xaf(100)->equals(Money::xaf(100)))->toBeTrue()
        ->and(Money::xaf(100)->equals(Money::of(100, 'EUR')))->toBeFalse();
});
