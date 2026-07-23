<?php

declare(strict_types=1);

namespace App\Domain\FollowUps\Actions;

use App\Domain\FollowUps\ChannelLadder;
use App\Domain\FollowUps\DoNotContactRefused;
use App\Domain\FollowUps\FollowUpKind;
use App\Domain\FollowUps\FollowUpScheduler;
use App\Models\DoNotContact;
use App\Models\FollowUp;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * A provider schedules a manual re-engagement nudge on a customer (build plan P7-08). It rides the
 * same table and the SAME budget + consent gates as automated follow-ups — a provider can't spam a
 * customer through the platform — and it is refused outright if the customer is on the provider's
 * do-not-contact list. `created_by_user_id` records who initiated it.
 */
final class ScheduleManualFollowUp
{
    public function __construct(
        private readonly FollowUpScheduler $scheduler,
        private readonly ChannelLadder $ladder,
    ) {}

    public function handle(User $provider, User $customer): FollowUp
    {
        if (DoNotContact::exists($provider->party_id, $customer->party_id)) {
            throw new DoNotContactRefused;
        }

        return $this->scheduler->schedule(
            FollowUpKind::Reengagement,
            $customer,
            $this->ladder->pick($customer),
            now(),
            'manual',
            Str::uuid()->toString(),
            createdByUserId: $provider->id,
        );
    }
}
