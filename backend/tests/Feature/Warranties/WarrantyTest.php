<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Domain\Warranties\Actions\IssueWarranty;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\OutboxMessage;
use App\Models\User;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P6-11 acceptance (doc 06): warranties + claims + remedy job spawning. The headline guarantee is
 * that a claim creates a real, linked remedy job with a real assignment — not an email thread.
 */

/**
 * @return array{customer: User, provider: User, engagement: Engagement}
 */
function warrantyEngagement(): array
{
    $customer = User::factory()->create();
    $job = Job::factory()->status(JobStatus::Engaged)->create([
        'customer_party_id' => $customer->party_id, 'created_by_user_id' => $customer->id,
    ]);
    $provider = User::factory()->create();
    $engagement = Engagement::factory()->create(['job_id' => $job->id, 'provider_party_id' => $provider->party_id]);
    Assignment::factory()->create([
        'engagement_id' => $engagement->id, 'worker_user_id' => $provider->id,
        'assigned_by_user_id' => $provider->id, 'role' => 'lead',
    ]);

    return compact('customer', 'provider', 'engagement');
}

it('lets the provider issue a warranty', function () {
    ['provider' => $provider, 'engagement' => $engagement] = warrantyEngagement();
    Sanctum::actingAs($provider);

    $this->postJson("/api/v1/engagements/{$engagement->id}/warranty", ['duration_days' => 90], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.duration_days', 90);
});

it('forbids a non-provider from issuing a warranty', function () {
    ['engagement' => $engagement] = warrantyEngagement();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/engagements/{$engagement->id}/warranty", ['duration_days' => 90], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertForbidden();
});

it('spawns a real remedy job with a real assignment when a claim is filed', function () {
    ['customer' => $customer, 'provider' => $provider, 'engagement' => $engagement] = warrantyEngagement();
    $warranty = app(IssueWarranty::class)->handle($engagement, 90);

    Sanctum::actingAs($customer);
    $claimId = $this->postJson("/api/v1/warranties/{$warranty->id}/claims", ['description' => 'Leak came back.'], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->json('data.id');

    $claim = WarrantyClaim::findOrFail($claimId);
    expect($claim->remedy_job_id)->not->toBeNull();

    // The remedy is a REAL, linked job...
    $remedyJob = Job::findOrFail($claim->remedy_job_id);
    expect($remedyJob->status)->toBe(JobStatus::Engaged)
        ->and($remedyJob->reference)->toStartWith('RMD-')
        ->and($remedyJob->customer_party_id)->toBe($customer->party_id);

    // ...with a real engagement whose origin is this claim, free (agreed 0)...
    $remedyEngagement = Engagement::query()->where('job_id', $remedyJob->id)->firstOrFail();
    expect($remedyEngagement->warranty_claim_id)->toBe($claim->id)
        ->and($remedyEngagement->agreed_amount_minor)->toBe(0);

    // ...and a real lead assignment to the ORIGINAL worker.
    $assignment = Assignment::query()->where('engagement_id', $remedyEngagement->id)->where('role', 'lead')->firstOrFail();
    expect($assignment->worker_user_id)->toBe($provider->id);

    expect($warranty->fresh()->status->value)->toBe('claimed');
    expect(OutboxMessage::query()->where('type', 'warranty.claim_filed')->exists())->toBeTrue();
});

it('rejects a claim on a non-active warranty (409)', function () {
    ['customer' => $customer, 'engagement' => $engagement] = warrantyEngagement();
    $warranty = Warranty::factory()->create(['engagement_id' => $engagement->id, 'status' => 'expired']);

    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/warranties/{$warranty->id}/claims", ['description' => 'x'], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(409);
});

it('forbids a non-customer from filing a claim', function () {
    ['engagement' => $engagement] = warrantyEngagement();
    $warranty = Warranty::factory()->create(['engagement_id' => $engagement->id]);

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/warranties/{$warranty->id}/claims", ['description' => 'x'], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertForbidden();
});
