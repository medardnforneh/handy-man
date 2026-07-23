<?php

declare(strict_types=1);

use App\Domain\Workspace\Actions\AutoApproveDeliverables;
use App\Models\Deliverable;
use App\Models\Engagement;
use App\Models\FollowUp;
use App\Models\Job;
use App\Models\User;
use App\Support\Outbox;
use App\Support\OutboxRelay;

/**
 * P3-11 acceptance (doc 03): the auto-approve timer. A submitted deliverable the customer never
 * reviews is auto-approved after the window, and a warning nudge is scheduled 24h before.
 */
it('auto-approves a deliverable past the review window', function () {
    $overdue = Deliverable::factory()->create(['status' => 'submitted', 'submitted_at' => now()->subHours(73)]);
    $fresh = Deliverable::factory()->create(['status' => 'submitted', 'submitted_at' => now()->subHours(2)]);

    expect(app(AutoApproveDeliverables::class)->handle())->toBe(1);

    expect($overdue->fresh()->status->value)->toBe('accepted')
        ->and($fresh->fresh()->status->value)->toBe('submitted');
});

it('schedules an auto-approve warning on submission and cancels it on review', function () {
    $customer = User::factory()->create();
    $job = Job::factory()->create(['customer_party_id' => $customer->party_id, 'created_by_user_id' => $customer->id]);
    $engagement = Engagement::factory()->create(['job_id' => $job->id]);
    $deliverable = Deliverable::factory()->create(['engagement_id' => $engagement->id, 'status' => 'submitted']);

    app(Outbox::class)->publish('deliverable.submitted', ['deliverable_id' => $deliverable->id, 'engagement_id' => $engagement->id]);
    app(OutboxRelay::class)->drain();

    expect(FollowUp::query()->where('kind', 'auto_approve_warning')->where('status', 'scheduled')->count())->toBe(1);

    app(Outbox::class)->publish('deliverable.accepted', ['deliverable_id' => $deliverable->id, 'engagement_id' => $engagement->id]);
    app(OutboxRelay::class)->drain();

    expect(FollowUp::query()->where('kind', 'auto_approve_warning')->where('status', 'scheduled')->count())->toBe(0)
        ->and(FollowUp::query()->where('kind', 'auto_approve_warning')->where('status', 'cancelled')->count())->toBe(1);
});
