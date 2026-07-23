<?php

declare(strict_types=1);

namespace App\Domain\Verification\Actions;

use App\Domain\Access\Facts\Fact;
use App\Domain\Access\Facts\FactDeriver;
use App\Domain\Verification\DocStatus;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A human admin approves or rejects a verification document (build plan P6-02/P6-03, doc 04). The
 * only path to verification here — there is no API background check in-market. An approval **raises
 * the party's verification tier** (to the tier the document grants), which is what feeds the
 * `identity_verified` fact, so a tier-1 provider becomes able to accept a tier-2/3 on-site job only
 * after a real human reviewed a real document. The decision is written to the audit log, attributable
 * to the named reviewer.
 */
final class ReviewVerificationDocument
{
    public function __construct(
        private readonly ActivityLogger $log,
        private readonly FactDeriver $facts,
    ) {}

    public function approve(VerificationDocument $document, User $reviewer): VerificationDocument
    {
        return $this->settle($document, $reviewer, approve: true, reason: null);
    }

    public function reject(VerificationDocument $document, User $reviewer, string $reason): VerificationDocument
    {
        return $this->settle($document, $reviewer, approve: false, reason: $reason);
    }

    private function settle(VerificationDocument $document, User $reviewer, bool $approve, ?string $reason): VerificationDocument
    {
        if ($document->status !== DocStatus::Pending) {
            throw new RuntimeException('This document has already been reviewed.');
        }

        return DB::transaction(function () use ($document, $reviewer, $approve, $reason): VerificationDocument {
            $document->status = $approve ? DocStatus::Approved : DocStatus::Rejected;
            $document->reviewed_by_user_id = $reviewer->id;
            $document->reviewed_at = now();
            $document->reject_reason = $reason;
            $document->save();

            if ($approve) {
                $this->raiseTier($document);
            }

            $this->log->log(
                action: $approve ? 'verification_document.approved' : 'verification_document.rejected',
                subject: $document,
                actorUserId: $reviewer->id,
                context: $approve ? ['grants_tier' => $document->grants_tier] : ['reason' => $reason],
            );

            return $document;
        });
    }

    /**
     * Raise the party's provider profile to the tier this document grants (never lower it), then
     * invalidate the cached identity fact for the affected human so the gate recomputes.
     */
    private function raiseTier(VerificationDocument $document): void
    {
        $profile = ProviderProfile::query()->where('party_id', $document->party_id)->first();

        if ($profile !== null && $document->grants_tier > $profile->verification_tier) {
            $profile->verification_tier = $document->grants_tier;
            $profile->save();
        }

        $subjectUserId = $document->subject_user_id;
        if ($subjectUserId !== null) {
            $subject = User::query()->find($subjectUserId);
            if ($subject !== null) {
                $this->facts->forget($subject, Fact::IdentityVerified);
            }
        }
    }
}
