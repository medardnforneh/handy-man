<?php

declare(strict_types=1);

use App\Domain\Engagements\Actions\CompleteEngagement;
use App\Domain\Jobs\Actions\PublishJob;
use App\Domain\Offers\Actions\CreateDirectOffer;
use App\Domain\Quotations\Actions\CompleteSiteVisit;
use App\Domain\Quotations\Actions\ScheduleSiteVisit;
use App\Domain\Reviews\Actions\SubmitReview;
use App\Domain\Warranties\Actions\IssueWarranty;
use App\Domain\Workspace\Actions\ReviewDeliverable;
use App\Domain\Workspace\Actions\SubmitDeliverable;
use App\Models\Engagement;
use App\Models\FollowUp;
use App\Models\Job;
use App\Models\Skill;
use App\Models\User;
use App\Support\Outbox;
use App\Support\OutboxRelay;
use Database\Seeders\SkillsSeeder;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P7-02 / P7-07 acceptance (doc 07): schedule on event, cancel on event; every follow-up has one
 * recordable response action.
 */

/**
 * @return array{customer: User, provider: User, engagement: Engagement}
 */
function orchestrationEngagement(): array
{
    $customer = User::factory()->create();
    $job = Job::factory()->create(['customer_party_id' => $customer->party_id, 'created_by_user_id' => $customer->id]);
    $provider = User::factory()->create();
    $engagement = Engagement::factory()->create(['job_id' => $job->id, 'provider_party_id' => $provider->party_id]);

    return compact('customer', 'provider', 'engagement');
}

it('schedules review follow-ups when an engagement completes, and cancels them on review (P7-02)', function () {
    ['customer' => $customer, 'engagement' => $engagement] = orchestrationEngagement();

    app(CompleteEngagement::class)->handle($engagement);
    app(OutboxRelay::class)->drain(); // the orchestrator runs off the outbox

    expect(FollowUp::query()->whereIn('kind', ['review_request', 'review_reminder'])->where('status', 'scheduled')->count())->toBe(2);

    // The customer submits their review → both nudges cancel.
    app(SubmitReview::class)->handle($engagement, $customer, 5);
    app(OutboxRelay::class)->drain();

    expect(FollowUp::query()->whereIn('kind', ['review_request', 'review_reminder'])->where('status', 'scheduled')->count())->toBe(0)
        ->and(FollowUp::query()->where('status', 'cancelled')->count())->toBe(2);
});

it('is idempotent — completing twice still yields exactly two review follow-ups', function () {
    ['engagement' => $engagement] = orchestrationEngagement();

    app(CompleteEngagement::class)->handle($engagement);
    app(OutboxRelay::class)->drain();
    app(CompleteEngagement::class)->handle($engagement); // no-op (already completed)
    app(OutboxRelay::class)->drain();

    expect(FollowUp::query()->count())->toBe(2);
});

it('schedules a warranty_expiring nudge 14 days before expiry (P7-07)', function () {
    ['engagement' => $engagement] = orchestrationEngagement();

    $warranty = app(IssueWarranty::class)->handle($engagement, 90);
    app(OutboxRelay::class)->drain();

    $followUp = FollowUp::query()->where('kind', 'warranty_expiring')->firstOrFail();
    expect($followUp->warranty_id)->toBe($warranty->id)
        ->and($followUp->scheduled_for->toDateString())->toBe($warranty->expires_at->copy()->subDays(14)->toDateString());
});

it('records a response action on a follow-up (P7-07)', function () {
    ['customer' => $customer] = orchestrationEngagement();
    $followUp = FollowUp::factory()->create(['target_user_id' => $customer->id, 'target_party_id' => $customer->party_id, 'status' => 'sent']);

    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/follow-ups/{$followUp->id}/respond", ['response_action' => 'review_submitted'], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertOk()
        ->assertJsonPath('data.status', 'responded')
        ->assertJsonPath('data.response_action', 'review_submitted');
});

it('forbids responding to someone else\'s follow-up', function () {
    $followUp = FollowUp::factory()->create(['status' => 'sent']);

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/follow-ups/{$followUp->id}/respond", ['response_action' => 'opened'], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertForbidden();
});

it('schedules maintenance_due only for a trade that genuinely recurs (P7-07)', function () {
    // An air conditioner really does want servicing again; the interval lives on the skill.
    $customer = User::factory()->create();
    $skill = Skill::factory()->create(['is_leaf' => true, 'maintenance_interval_days' => 180]);
    $job = Job::factory()->create([
        'customer_party_id' => $customer->party_id, 'created_by_user_id' => $customer->id, 'skill_id' => $skill->id,
    ]);
    $engagement = Engagement::factory()->create([
        'job_id' => $job->id, 'provider_party_id' => User::factory()->create()->party_id,
    ]);

    app(CompleteEngagement::class)->handle($engagement);
    app(OutboxRelay::class)->drain();

    $followUp = FollowUp::query()->where('kind', 'maintenance_due')->first();
    expect($followUp)->not->toBeNull()
        // Anchored to COMPLETION, not to the moment the outbox happened to be relayed.
        ->and($followUp->scheduled_for->toDateString())
        ->toBe($engagement->fresh()->completed_at->copy()->addDays(180)->toDateString());
});

it('schedules no maintenance nudge for a one-off trade', function () {
    // A wardrobe built once does not need servicing, and a reminder saying it does is exactly the
    // message that teaches a customer to ignore the channel.
    $customer = User::factory()->create();
    $skill = Skill::factory()->create(['is_leaf' => true, 'maintenance_interval_days' => null]);
    $job = Job::factory()->create([
        'customer_party_id' => $customer->party_id, 'created_by_user_id' => $customer->id, 'skill_id' => $skill->id,
    ]);
    $engagement = Engagement::factory()->create([
        'job_id' => $job->id, 'provider_party_id' => User::factory()->create()->party_id,
    ]);

    app(CompleteEngagement::class)->handle($engagement);
    app(OutboxRelay::class)->drain();

    expect(FollowUp::query()->where('kind', 'maintenance_due')->count())->toBe(0)
        // The review nudges still fire — this gate is about maintenance only.
        ->and(FollowUp::query()->where('kind', 'review_request')->count())->toBe(1);
});

it('seeds maintenance intervals only on trades that recur', function () {
    $this->seed(SkillsSeeder::class);

    expect(Skill::query()->where('slug', 'ac-maintenance')->value('maintenance_interval_days'))->toBe(180)
        ->and(Skill::query()->where('slug', 'custom-furniture')->value('maintenance_interval_days'))->toBeNull()
        // The list is deliberately short: most of the taxonomy schedules nothing.
        ->and(Skill::query()->whereNotNull('maintenance_interval_days')->count())->toBeLessThan(10);
});

it('nudges a customer whose published job nobody has offered on, and stops the moment one does', function () {
    // doc 07: a job open with no offers is the customer's most likely moment to give up on us.
    $customer = User::factory()->create();
    // The factory already defaults to draft, which is what PublishJob expects.
    $job = Job::factory()->create([
        'customer_party_id' => $customer->party_id, 'created_by_user_id' => $customer->id,
    ]);

    app(PublishJob::class)->handle($job);
    app(OutboxRelay::class)->drain();

    expect(FollowUp::query()->where('kind', 'job_unquoted')->where('status', 'scheduled')->count())->toBe(1);

    app(CreateDirectOffer::class)->handle($job->fresh(), User::factory()->create()->party_id);
    app(OutboxRelay::class)->drain();

    expect(FollowUp::query()->where('kind', 'job_unquoted')->where('status', 'scheduled')->count())->toBe(0)
        ->and(FollowUp::query()->where('kind', 'job_unquoted')->where('status', 'cancelled')->count())->toBe(1);
});

it('reminds a customer of a site visit a day and two hours out, and cancels once it happens', function () {
    $customer = User::factory()->create();
    $provider = User::factory()->create();
    $job = Job::factory()->create(['customer_party_id' => $customer->party_id, 'created_by_user_id' => $customer->id]);
    $at = now()->addDays(5);

    $visit = app(ScheduleSiteVisit::class)->handle($provider, $job, $at, false, 0);
    app(OutboxRelay::class)->drain();

    $due = FollowUp::query()->where('kind', 'site_visit_reminder')->orderBy('scheduled_for')->pluck('scheduled_for');
    expect($due)->toHaveCount(2)
        ->and($due[0]->toDateTimeString())->toBe($at->copy()->subHours(24)->toDateTimeString())
        ->and($due[1]->toDateTimeString())->toBe($at->copy()->subHours(2)->toDateTimeString());

    app(CompleteSiteVisit::class)->handle($visit, null, null);
    app(OutboxRelay::class)->drain();

    expect(FollowUp::query()->where('kind', 'site_visit_reminder')->where('status', 'scheduled')->count())->toBe(0);
});

// `grantPayable()` is declared in tests/Feature/Money/PayoutTest.php — Pest test helpers are global
// once loaded, so it is reused here rather than redeclared (which is a fatal error).
it('tells a provider their money is withdrawable once the balance is worth withdrawing', function () {
    // doc 07 `payout_ready`. Nudging someone to withdraw a pittance costs us a message and costs
    // them a transfer fee, so it is gated on a threshold rather than fired on every release.
    config()->set('followups.payout_ready_threshold_minor', 100_000);

    ['engagement' => $engagement, 'provider' => $provider] = orchestrationEngagement();

    grantPayable($provider, 20_000);
    app(Outbox::class)->publish('milestone.approved', ['engagement_id' => $engagement->id]);
    app(OutboxRelay::class)->drain();
    expect(FollowUp::query()->where('kind', 'payout_ready')->count())->toBe(0);

    grantPayable($provider, 200_000); // now well over the threshold
    app(Outbox::class)->publish('milestone.approved', ['engagement_id' => $engagement->id]);
    app(OutboxRelay::class)->drain();
    expect(FollowUp::query()->where('kind', 'payout_ready')->count())->toBe(1);
});

it('nudges a customer that submitted work is waiting on them, and stops when they review it', function () {
    ['engagement' => $engagement, 'provider' => $provider, 'customer' => $customer] = orchestrationEngagement();

    $deliverable = app(SubmitDeliverable::class)->handle($provider, $engagement, 'Design files');
    app(OutboxRelay::class)->drain();

    // Two distinct messages: "this is waiting on you", and later "it auto-approves tomorrow".
    expect(FollowUp::query()->where('kind', 'awaiting_approval')->where('status', 'scheduled')->count())->toBe(1)
        ->and(FollowUp::query()->where('kind', 'auto_approve_warning')->where('status', 'scheduled')->count())->toBe(1);

    app(ReviewDeliverable::class)->handle($deliverable, true);
    app(OutboxRelay::class)->drain();

    expect(FollowUp::query()->whereIn('kind', ['awaiting_approval', 'auto_approve_warning'])->where('status', 'scheduled')->count())->toBe(0);
});
