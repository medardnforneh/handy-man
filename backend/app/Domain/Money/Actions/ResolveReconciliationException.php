<?php

declare(strict_types=1);

namespace App\Domain\Money\Actions;

use App\Domain\Money\Ledger;
use App\Domain\Money\LedgerEntryInput;
use App\Domain\Money\TxnKind;
use App\Models\ReconciliationException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A human resolves a reconciliation exception (build plan P3-09, doc 03). The correction is always a
 * balanced adjustment transaction stamped with the resolver's `created_by_user_id` — never an edit of
 * history. Passing no entries records the resolution without a ledger movement (e.g. a false alarm).
 */
final class ResolveReconciliationException
{
    public function __construct(private readonly Ledger $ledger) {}

    /**
     * @param  list<LedgerEntryInput>  $adjustmentEntries
     */
    public function handle(User $resolver, ReconciliationException $exception, array $adjustmentEntries, string $memo): void
    {
        DB::transaction(function () use ($resolver, $exception, $adjustmentEntries, $memo): void {
            $txnId = null;
            if ($adjustmentEntries !== []) {
                $txn = $this->ledger->post(
                    TxnKind::Adjustment,
                    $adjustmentEntries,
                    referenceType: 'reconciliation_exception',
                    referenceId: $exception->id,
                    memo: $memo,
                    createdByUserId: $resolver->id,
                );
                $txnId = $txn->id;
            }

            $exception->update([
                'status' => ReconciliationException::STATUS_RESOLVED,
                'resolved_at' => now(),
                'resolved_by_user_id' => $resolver->id,
                'resolution_transaction_id' => $txnId,
            ]);
        });
    }
}
