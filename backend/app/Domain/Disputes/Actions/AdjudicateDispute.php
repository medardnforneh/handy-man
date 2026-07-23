<?php

declare(strict_types=1);

namespace App\Domain\Disputes\Actions;

use App\Domain\Money\Ledger;
use App\Domain\Money\LedgerEntryInput;
use App\Domain\Money\TxnKind;
use App\Models\Dispute;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A human admin adjudicates a dispute (build plan P6-10, doc 04). Any money effect is a **balanced
 * adjustment transaction** stamped with the admin's id and referenced to the dispute — never an edit
 * of history. Passing no entries records a resolution with no ledger movement (e.g. dismissed as
 * unfounded). The decision is written to the audit log, attributable to the named admin.
 *
 * @phpstan-type Decision 'resolved'|'rejected'
 */
final class AdjudicateDispute
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly ActivityLogger $log,
    ) {}

    /**
     * @param  'resolved'|'rejected'  $decision
     * @param  list<LedgerEntryInput>  $adjustmentEntries
     */
    public function handle(
        Dispute $dispute,
        User $admin,
        string $decision,
        ?string $resolutionNote = null,
        array $adjustmentEntries = [],
        ?string $memo = null,
    ): Dispute {
        if (! in_array($dispute->status, ['open', 'reviewing'], true)) {
            throw new RuntimeException('This dispute has already been adjudicated.');
        }

        return DB::transaction(function () use ($dispute, $admin, $decision, $resolutionNote, $adjustmentEntries, $memo): Dispute {
            $txnId = null;
            if ($adjustmentEntries !== []) {
                $txn = $this->ledger->post(
                    TxnKind::Adjustment,
                    $adjustmentEntries,
                    referenceType: 'dispute',
                    referenceId: $dispute->id,
                    memo: $memo ?? 'Dispute adjudication',
                    createdByUserId: $admin->id,
                );
                $txnId = $txn->id;
            }

            $dispute->update([
                'status' => $decision,
                'resolution_note' => $resolutionNote,
                'resolution_transaction_id' => $txnId,
                'resolved_by_user_id' => $admin->id,
                'resolved_at' => now(),
            ]);

            $this->log->log(
                action: 'dispute.adjudicated',
                subject: $dispute,
                actorUserId: $admin->id,
                context: ['decision' => $decision, 'transaction_id' => $txnId],
            );

            return $dispute;
        });
    }
}
