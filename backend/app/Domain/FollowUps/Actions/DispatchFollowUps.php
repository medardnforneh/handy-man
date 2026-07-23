<?php

declare(strict_types=1);

namespace App\Domain\FollowUps\Actions;

use App\Domain\FollowUps\CommsBudget;
use App\Domain\FollowUps\FollowUpStatus;
use App\Domain\Identity\Consent\ConsentState;
use App\Models\CommsLog;
use App\Models\FollowUp;
use App\Models\User;

/**
 * Dispatches due follow-ups (doc 07). For each still-scheduled, due row it applies the two gates in
 * order — **consent** (a marketing kind with `marketing` revoked is suppressed) then **budget** (a
 * non-transactional send over the channel cap is suppressed) — and otherwise sends, recording the
 * send in `comms_log` (which is what the budget counts). Scheduled to run frequently; idempotent by
 * construction (it only touches `scheduled` rows).
 */
final class DispatchFollowUps
{
    public function __construct(
        private readonly CommsBudget $budget,
        private readonly ConsentState $consent,
    ) {}

    /**
     * Processes all due follow-ups. Returns the number actually sent.
     */
    public function handle(): int
    {
        $due = FollowUp::query()
            ->where('status', FollowUpStatus::Scheduled->value)
            ->where('scheduled_for', '<=', now())
            ->orderBy('scheduled_for')
            ->get();

        $sent = 0;

        foreach ($due as $followUp) {
            $user = User::query()->find($followUp->target_user_id);
            if ($user === null) {
                $followUp->update(['status' => FollowUpStatus::Failed->value, 'failure_reason' => 'no_target_user']);

                continue;
            }

            $kind = $followUp->kind;

            if ($kind->requiresMarketingConsent() && ! $this->consent->has($user, 'marketing')) {
                $followUp->update(['status' => FollowUpStatus::Suppressed->value, 'failure_reason' => 'consent_revoked']);

                continue;
            }

            if (! $kind->isTransactional() && ! $this->budget->allows($user, $followUp->channel)) {
                $followUp->update(['status' => FollowUpStatus::Suppressed->value, 'failure_reason' => 'over_budget']);

                continue;
            }

            // Deliver. The comms_log entry IS the record of the send (and what the budget counts); the
            // per-channel transport (push/WhatsApp/SMS) plugs in behind the channel in P7-05/06.
            CommsLog::query()->create([
                'user_id' => $user->id,
                'channel' => $followUp->channel->value,
                'purpose' => $kind->value,
                'follow_up_id' => $followUp->id,
                'sent_at' => now(),
            ]);

            $followUp->update([
                'status' => FollowUpStatus::Sent->value,
                'sent_at' => now(),
                'attempts' => $followUp->attempts + 1,
            ]);

            $sent++;
        }

        return $sent;
    }
}
