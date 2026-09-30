<?php

declare(strict_types=1);

namespace App\Domain\FollowUps\Actions;

use App\Domain\FollowUps\ChannelLadder;
use App\Domain\FollowUps\DoNotContactRefused;
use App\Domain\FollowUps\FollowUpKind;
use App\Domain\FollowUps\FollowUpScheduler;
use App\Domain\FollowUps\NotYourCustomer;
use App\Models\DoNotContact;
use App\Models\Engagement;
use App\Models\FollowUp;
use App\Models\Job;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * A provider schedules a manual re-engagement nudge on a customer (build plan P7-08). It rides the
 * same table and the SAME budget + consent gates as automated follow-ups, is refused outright if
 * the customer is on the provider's do-not-contact list, and — since 2026-09-30 — is refused unless
 * the two have actually worked together. `created_by_user_id` records who initiated it.
 *
 * "A provider can't spam a customer through the platform" is what this comment used to claim on the
 * strength of the budget and the do-not-contact list alone. The budget bounds how MUCH; the
 * do-not-contact list is the provider's own and bounds nothing they do not choose to. Neither
 * bounded WHO, so the endpoint would nudge any party id handed to it.
 */
final class ScheduleManualFollowUp
{
    public function __construct(
        private readonly FollowUpScheduler $scheduler,
        private readonly ChannelLadder $ladder,
    ) {}

    public function handle(User $provider, User $customer): FollowUp
    {
        // First: is this even their customer? The docblock above has always claimed a provider
        // cannot spam a customer through the platform, and the only guards were the budget and a
        // do-not-contact list the PROVIDER controls — so the claim held for volume and not at all
        // for who. An engagement between the two is what the client book means by "customer"
        // (ProviderCustomers joins exactly this), so it is what is required here.
        $shared = Engagement::query()
            ->where('provider_party_id', $provider->party_id)
            ->whereIn('job_id', Job::query()->select('id')->where('customer_party_id', $customer->party_id))
            ->exists();

        if (! $shared) {
            throw new NotYourCustomer;
        }

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
