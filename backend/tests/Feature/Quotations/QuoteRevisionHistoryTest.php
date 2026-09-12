<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Domain\Quotations\QuoteStatus;
use App\Models\Job;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * Launch checklist (doc 05): "A quote is revised three times; all versions visible; none mutated."
 *
 * The single-revision test proves the mechanism; this proves the SCENARIO the checklist names —
 * that a chain of revisions leaves a complete, readable, frozen history rather than a latest
 * version and three ghosts. The customer must be able to see what was asked for at each step,
 * because that is what a dispute is adjudicated against.
 */
function lineOf(int $quantity, int $unitPrice): array
{
    return ['kind' => 'labour', 'label' => 'Install', 'quantity' => $quantity, 'unit_price_minor' => $unitPrice];
}

/** @param  list<array<string, mixed>>  $lines */
function revisionPayload(array $lines): array
{
    return [
        'lines' => $lines,
        'deposit_minor' => 100000,
        'notes' => 'Revised',
        'valid_until' => now()->addDays(7)->toIso8601String(),
        'provider_committed_at' => now()->addDays(5)->toIso8601String(),
    ];
}

it('revises a quote three times, keeps every version visible to the customer, and freezes each one', function () {
    $customer = User::factory()->create();
    $job = Job::factory()->remote()->status(JobStatus::Open)->create(['customer_party_id' => $customer->party_id]);
    $provider = User::factory()->create();

    // v1 → v2 → v3 → v4, each a different subtotal so a version can be told apart by its money.
    $subtotals = [900000, 600000, 750000, 810000];
    $lines = [[lineOf(3, 300000)], [lineOf(2, 300000)], [lineOf(3, 250000)], [lineOf(3, 270000)]];

    Sanctum::actingAs($provider);
    $ids = [];
    $ids[0] = $this->postJson("/api/v1/jobs/{$job->id}/quotations", revisionPayload($lines[0]), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()->json('data.id');

    foreach ([1, 2, 3] as $i) {
        $ids[$i] = $this->postJson("/api/v1/quotations/{$ids[$i - 1]}/revise", revisionPayload($lines[$i]), ['Idempotency-Key' => (string) Str::uuid()])
            ->assertCreated()
            ->assertJsonPath('data.version', $i + 1)
            ->assertJsonPath('data.supersedes_id', $ids[$i - 1])
            ->assertJsonPath('data.subtotal.amount_minor', $subtotals[$i])
            ->json('data.id');
    }

    // Exactly one live quote; the three earlier versions are superseded, not deleted or rewritten.
    $all = Quotation::query()->where('job_id', $job->id)->orderBy('version')->get();
    expect($all)->toHaveCount(4)
        ->and($all->pluck('version')->all())->toBe([1, 2, 3, 4])
        ->and($all->pluck('subtotal_minor')->all())->toBe($subtotals)
        ->and($all->take(3)->pluck('status')->all())->toBe([QuoteStatus::Superseded, QuoteStatus::Superseded, QuoteStatus::Superseded])
        ->and($all->last()->status)->toBe(QuoteStatus::Submitted)
        ->and($all->pluck('supersedes_id')->all())->toBe([null, $ids[0], $ids[1], $ids[2]]);

    // The customer sees the whole chain, newest first, with each version's own lines and money.
    Sanctum::actingAs($customer);
    $seen = $this->getJson("/api/v1/jobs/{$job->id}/quotations")->assertOk()->json('data');
    expect(array_column($seen, 'version'))->toBe([4, 3, 2, 1])
        ->and(array_column($seen, 'id'))->toBe(array_reverse($ids))
        ->and(array_map(fn (array $q): int => $q['subtotal']['amount_minor'], $seen))->toBe(array_reverse($subtotals))
        ->and(array_map(fn (array $q): float => (float) $q['lines'][0]['quantity'], $seen))->toBe([3.0, 3.0, 2.0, 3.0]);

    // None of them can be mutated — not the superseded ones, not the live one. The freeze is the
    // database's, so no code path above it can quietly rewrite history. Each attempt runs in its
    // own savepoint: a raised exception aborts the enclosing transaction, and the test suite wraps
    // every test in one.
    foreach ($ids as $id) {
        expect(fn () => DB::transaction(fn () => DB::table('quotations')->where('id', $id)->update(['subtotal_minor' => 1])))
            ->toThrow(QueryException::class, 'immutable');
        expect(fn () => DB::transaction(fn () => QuotationLine::query()->where('quotation_id', $id)->update(['quantity' => 99])))
            ->toThrow(QueryException::class, 'frozen');
    }
});
