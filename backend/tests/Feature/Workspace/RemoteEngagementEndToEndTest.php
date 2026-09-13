<?php

declare(strict_types=1);

use App\Domain\Jobs\EngagementMode;
use App\Domain\Jobs\EngagementModePolicy;
use App\Domain\Jobs\JobStatus;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * Launch checklist (doc 05): "A remote engagement completes end-to-end with no address, no
 * check-in, no panic affordance."
 *
 * The unit tests prove each refusal on its own; this walks the whole remote path as the two people
 * in it would — quote, acceptance, work, deliverable, review, completion — and at every step asks
 * for the on-site things (an address, a check-in, a shared visit) to be sure the remote path never
 * needs them and never offers them. Panic is a person's affordance, not an engagement's: the Safety
 * page is reachable from anywhere by design and the server never refuses a panic, whatever the
 * job; what a remote engagement carries is the POLICY saying its screens surface none.
 */
it('takes a remote job from request to completion with no address, no check-in and no site affordances', function () {
    $customer = User::factory()->create();
    $provider = User::factory()->create();
    $key = fn (): array => ['Idempotency-Key' => (string) Str::uuid()];

    // 1. A remote request: no address, and the DB would not accept one being required.
    $job = Job::factory()->remote()->status(JobStatus::Open)->create([
        'customer_party_id' => $customer->party_id,
        'created_by_user_id' => $customer->id,
    ]);
    expect($job->address_id)->toBeNull();
    $policy = app(EngagementModePolicy::class)->forJob($job);
    expect($policy['requires_address'] ?? $policy['address'] ?? false)->toBeFalse()
        ->and(app(EngagementModePolicy::class)->supportsCheckIn(EngagementMode::Remote))->toBeFalse()
        ->and(app(EngagementModePolicy::class)->supportsPanic(EngagementMode::Remote))->toBeFalse()
        ->and(app(EngagementModePolicy::class)->supportsShareJob(EngagementMode::Remote))->toBeFalse()
        ->and(app(EngagementModePolicy::class)->usesDeliverables(EngagementMode::Remote))->toBeTrue();

    // 2. The provider quotes, the customer accepts: an engagement with no address behind it.
    Sanctum::actingAs($provider);
    $quoteId = $this->postJson("/api/v1/jobs/{$job->id}/quotations", [
        'lines' => [['kind' => 'labour', 'label' => 'Logo and brand sheet', 'quantity' => 1, 'unit_price_minor' => 250000]],
        'deposit_minor' => 50000,
        'valid_until' => now()->addDays(5)->toIso8601String(),
        'provider_committed_at' => now()->addDays(3)->toIso8601String(),
    ], $key())->assertCreated()->json('data.id');

    Sanctum::actingAs($customer);
    $engagementId = $this->postJson("/api/v1/quotations/{$quoteId}/accept", [], $key())
        ->assertCreated()
        ->json('data.id');
    $engagement = Engagement::query()->findOrFail($engagementId);
    expect($engagement->job->address_id)->toBeNull();

    // 3. The provider's view of the work carries no address and no check-in, and a check-in is refused.
    Sanctum::actingAs($provider);
    $this->getJson("/api/v1/provider/work/{$engagementId}")
        ->assertOk()
        ->assertJsonPath('data.engagement_mode', 'remote')
        ->assertJsonPath('data.address', null)
        ->assertJsonPath('data.supports_check_in', false)
        ->assertJsonPath('data.supports_report', false)
        ->assertJsonPath('data.uses_deliverables', true);
    $this->postJson("/api/v1/engagements/{$engagementId}/check-in", [], $key())
        ->assertStatus(422)
        ->assertJsonPath('type', fn (string $t): bool => str_ends_with($t, 'check-in-not-supported'));

    // 4. Sharing a live visit — the on-site safety affordance — is refused for the same reason.
    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/engagements/{$engagementId}/share", [], $key())
        ->assertStatus(422)
        ->assertJsonPath('type', fn (string $t): bool => str_contains($t, 'share'));

    // 5. The work happens remotely: status, then a deliverable the customer accepts.
    Sanctum::actingAs($provider);
    $this->postJson("/api/v1/engagements/{$engagementId}/status", ['status' => 'started'], $key())->assertCreated();
    expect(Job::query()->findOrFail($job->id)->status)->toBe(JobStatus::InProgress);
    $deliverableId = $this->postJson("/api/v1/engagements/{$engagementId}/deliverables", [
        'title' => 'Logo pack', 'media_url' => 'deliverables/logo.zip',
    ], $key())->assertCreated()->json('data.id');
    expect(Job::query()->findOrFail($job->id)->status)->toBe(JobStatus::WorkSubmitted);

    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/deliverables/{$deliverableId}/review", ['decision' => 'accept'], $key())
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    // 6. Completion, with nothing on-site ever having been asked for.
    $this->postJson("/api/v1/engagements/{$engagementId}/complete", [], $key())->assertOk();

    $engagement->refresh();
    expect($engagement->completed_at)->not->toBeNull()
        ->and(Job::query()->findOrFail($job->id)->status)->toBe(JobStatus::Completed)
        ->and($engagement->job->address_id)->toBeNull();

    $this->getJson("/api/v1/jobs/{$job->id}")
        ->assertOk()
        ->assertJsonPath('data.engagement.completed_at', fn ($v) => $v !== null)
        ->assertJsonPath('data.address', null);
});
