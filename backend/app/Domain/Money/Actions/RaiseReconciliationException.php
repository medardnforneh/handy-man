<?php

declare(strict_types=1);

namespace App\Domain\Money\Actions;

use App\Models\ReconciliationException;
use App\Support\Outbox;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Record a reconciliation discrepancy and alert an admin (build plan P3-09). Never corrects anything.
 * Idempotent per (kind, reference) while open — a nightly re-run of the same unresolved discrepancy
 * won't pile up duplicates (partial unique index).
 */
final class RaiseReconciliationException
{
    public function __construct(private readonly Outbox $outbox) {}

    public function handle(
        string $kind,
        string $detail,
        ?int $amountMinor = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
    ): ?ReconciliationException {
        try {
            $exception = DB::transaction(fn (): ReconciliationException => ReconciliationException::query()->create([
                'kind' => $kind,
                'detail' => $detail,
                'amount_minor' => $amountMinor,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'status' => ReconciliationException::STATUS_OPEN,
                'detected_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            return null; // already open — don't re-alert
        }

        $this->outbox->publish('reconciliation.exception', [
            'reconciliation_exception_id' => $exception->id,
            'kind' => $kind,
            'amount_minor' => $amountMinor,
        ]);

        return $exception;
    }
}
